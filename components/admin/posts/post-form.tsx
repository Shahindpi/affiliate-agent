"use client";
import { routes } from "@/lib/routes";

import { useEffect, useState } from "react";
import slugify from "slugify";
import { Controller, useForm, useWatch } from "react-hook-form";

import { zodResolver } from "@hookform/resolvers/zod";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { toast } from "sonner";
import { useRouter } from "next/navigation";

import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";

import { postSchema, PostFormValues } from "@/schemas/post-schema";

import api from "@/lib/axios";
import { allResourceOptions } from "@/services/admin-resources";
import type { Category } from "@/types/category";

import { createPost, updatePost } from "@/services/posts";

import PostSlugInput from "./post-slug-input";
import PostEditorSidebar from "./post-editor-sidebar";

import RichTextEditor from "@/components/admin/editor/rich-text-editor";

interface Props {
  mode: "create" | "edit";

  post?: Partial<PostFormValues> & {
    id?: number;
    featured_image?: string | null;
    featured_image_path?: string | null;
    tags?: { id: number; name: string }[];
    affiliate_products?: { id: number; name: string }[];
    seo?: {
      meta_title?: string;
      meta_description?: string;
      canonical_url?: string;
      overrides?: {
        meta_title?: string | null;
        meta_description?: string | null;
        canonical_url?: string | null;
        og_image?: string | null;
      };
      has_overrides?: boolean;
    };
  };
}

export default function PostForm({ mode, post }: Props) {
  const router = useRouter();
  const queryClient = useQueryClient();
  // Keep the created ID if a later relationship/SEO request fails, so Retry
  // updates that post instead of creating a duplicate.
  const [savedId, setSavedId] = useState(post?.id);

  /*
  |--------------------------------------------------------------------------
  | Featured Image
  |--------------------------------------------------------------------------
  */

  const [tagIds, setTagIds] = useState<number[]>(
    post?.tags?.map((tag) => tag.id) || [],
  );
  const [productIds, setProductIds] = useState<number[]>(
    post?.affiliate_products?.map((product) => product.id) || [],
  );
  const [seoTitle, setSeoTitle] = useState(
    post?.seo?.overrides?.meta_title || "",
  );
  const [seoDescription, setSeoDescription] = useState(
    post?.seo?.overrides?.meta_description || "",
  );
  const [canonicalUrl, setCanonicalUrl] = useState(
    post?.seo?.overrides?.canonical_url || "",
  );
  const [socialImage, setSocialImage] = useState(
    post?.seo?.overrides?.og_image || "",
  );
  const { data: availableTags = [] } = useQuery({
    queryKey: ["post-tags"],
    queryFn: () => allResourceOptions<{ id: number; name: string }>("tags"),
  });
  const { data: availableProducts = [] } = useQuery({
    queryKey: ["post-products"],
    queryFn: () => allResourceOptions<{ id: number; name: string }>("affiliate-products"),
  });

  const [featuredImage, setFeaturedImage] = useState<string | null>(
    post?.featured_image_path ?? post?.featured_image ?? null,
  );

  /*
  |--------------------------------------------------------------------------
  | Form
  |--------------------------------------------------------------------------
  */

  const {
    register,
    control,
    handleSubmit,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<PostFormValues>({
    resolver: zodResolver(postSchema),

    defaultValues: {
      title: post?.title ?? "",
      slug: post?.slug ?? "",
      excerpt: post?.excerpt ?? "",
      content: post?.content ?? "",
      status: post?.status ?? "draft",
      post_type: post?.post_type ?? "article",
      allow_comments: post?.allow_comments ?? true,
      category_id: post?.category_id ?? 0,
    },
  });

  const { data: categories = [] } = useQuery({
    queryKey: ["post-categories"],
    queryFn: () => allResourceOptions<Category>("categories"),
  });

  const title = useWatch({ control, name: "title" });
  const slug = useWatch({ control, name: "slug" });
  const status = useWatch({ control, name: "status" });

  /*
  |--------------------------------------------------------------------------
  | Auto Generate Slug
  |--------------------------------------------------------------------------
  */

  useEffect(() => {
    if (mode !== "create") {
      return;
    }

    const generatedSlug = slugify(title || "", {
      lower: true,
      strict: true,
    });

    setValue("slug", generatedSlug, {
      shouldDirty: true,
      shouldTouch: true,
      shouldValidate: true,
    });
  }, [title, mode, setValue]);

  /*
  |--------------------------------------------------------------------------
  | Save Mutation
  |--------------------------------------------------------------------------
  */

  const mutation = useMutation({
    mutationFn: async (values: PostFormValues) => {
      const payload = {
        ...values,
        category_id: values.category_id || null,
        featured_image: featuredImage,
      };

      const saved =
        savedId
          ? await updatePost(savedId, payload)
          : await createPost(payload);
      if (saved?.id) {
        setSavedId(saved.id);
        await api.put(`/admin/posts/${saved.id}/tags`, { tag_ids: tagIds });
        await api.put(`/admin/posts/${saved.id}/affiliate-products`, {
          products: productIds.map((id, sort_order) => ({
            affiliate_product_id: id,
            sort_order,
            is_primary: sort_order === 0,
          })),
        });
        if (seoTitle || seoDescription || canonicalUrl || socialImage) {
          await api.put(`/admin/posts/${saved.id}/seo`, {
            meta_title: seoTitle || null,
            meta_description: seoDescription || null,
            canonical_url: canonicalUrl || null,
            og_image: socialImage || null,
          });
        } else if (post?.seo?.has_overrides) {
          await api.delete(`/admin/posts/${saved.id}/seo`);
        }
      }
      return saved;
    },

    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["posts"],
      });

      toast.success(
        mode === "create"
          ? "Post created successfully."
          : "Post updated successfully.",
      );

      router.push(routes.admin.posts.index);
    },

    onError: (error: {
      response?: { data?: { message?: string } };
      message?: string;
    }) => {
      toast.error(
        error?.response?.data?.message ??
          error?.message ??
          "Unable to save post.",
      );
    },
  });

  /*
  |--------------------------------------------------------------------------
  | Submit
  |--------------------------------------------------------------------------
  */

  const onSubmit = (values: PostFormValues) => {
    mutation.mutate(values);
  };

  /*
  |--------------------------------------------------------------------------
  | Invalid Submit
  |--------------------------------------------------------------------------
  */

  const onInvalid = (formErrors: typeof errors) => {
    const firstError = Object.values(formErrors)[0];

    if (firstError?.message) {
      toast.error(String(firstError.message));
    } else {
      toast.error("Please check the form fields.");
    }
  };

  /*
  |--------------------------------------------------------------------------
  | Explicit Save Handler
  |--------------------------------------------------------------------------
  |
  | This bypasses relying on the browser's native form submit event.
  | React Hook Form performs validation and then calls onSubmit.
  |
  */

  const handleSave = () => {
    handleSubmit(onSubmit, onInvalid)();
  };

  /*
  |--------------------------------------------------------------------------
  | Render
  |--------------------------------------------------------------------------
  */

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();

        handleSubmit(onSubmit, onInvalid)();
      }}
      className="grid grid-cols-1 gap-8 xl:grid-cols-[1fr_320px]"
    >
      {/* ------------------------------------------------------------------ */}
      {/* Main Editor */}
      {/* ------------------------------------------------------------------ */}

      <div className="space-y-6 rounded-2xl border bg-background p-6">
        {/* Title */}
        <div className="space-y-2">
          <label htmlFor="title" className="text-sm font-medium">
            Title
          </label>

          <Input
            id="title"
            placeholder="Enter post title..."
            {...register("title")}
          />

          {errors.title && (
            <p className="text-sm text-destructive">{errors.title.message}</p>
          )}
        </div>

        {/* Slug */}
        <PostSlugInput
          value={slug}
          onChange={(value) => {
            setValue("slug", value, {
              shouldDirty: true,
              shouldTouch: true,
              shouldValidate: true,
            });
          }}
        />

        <input type="hidden" {...register("slug")} />

        {errors.slug && (
          <p className="text-sm text-destructive">{errors.slug.message}</p>
        )}

        {/* Excerpt */}
        <div className="space-y-2">
          <label htmlFor="excerpt" className="text-sm font-medium">
            Excerpt
          </label>

          <Textarea
            id="excerpt"
            rows={4}
            placeholder="Short description for blog listing and SEO..."
            {...register("excerpt")}
          />

          {errors.excerpt && (
            <p className="text-sm text-destructive">{errors.excerpt.message}</p>
          )}
        </div>

        <div className="space-y-2">
          <label htmlFor="category_id" className="text-sm font-medium">
            Category
          </label>
          <select
            id="category_id"
            {...register("category_id", { valueAsNumber: true })}
            className="w-full rounded-lg border p-3"
          >
            <option value="0">No category</option>
            {categories.map((category) => (
              <option key={category.id} value={category.id}>
                {category.name}
              </option>
            ))}
          </select>
        </div>
        <div className="space-y-2">
          <label htmlFor="post_type" className="text-sm font-medium">
            Post type
          </label>
          <select
            id="post_type"
            {...register("post_type")}
            className="w-full rounded-lg border p-3"
          >
            {["article", "review", "comparison", "tutorial", "news"].map(
              (type) => (
                <option key={type} value={type}>
                  {type}
                </option>
              ),
            )}
          </select>
        </div>

        {/* Content */}
        <div className="space-y-2">
          <label className="text-sm font-medium">Content</label>

          <Controller
            name="content"
            control={control}
            render={({ field }) => (
              <RichTextEditor
                value={field.value ?? ""}
                onChange={field.onChange}
                imageAltFallback={title || "Post image"}
              />
            )}
          />

          {errors.content && (
            <p className="text-sm text-destructive">{errors.content.message}</p>
          )}
        </div>

        <div className="space-y-3">
          <h3 className="text-sm font-medium">Tags</h3>
          <div className="flex flex-wrap gap-3">
            {availableTags.map((tag) => (
              <label key={tag.id} className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={tagIds.includes(tag.id)}
                  onChange={(e) =>
                    setTagIds((ids) =>
                      e.target.checked
                        ? [...ids, tag.id]
                        : ids.filter((id) => id !== tag.id),
                    )
                  }
                />
                {tag.name}
              </label>
            ))}
          </div>
        </div>
        <fieldset className="space-y-3 rounded-xl border p-4">
          <legend className="px-1 text-sm font-semibold">Products mentioned</legend>
          <p className="text-sm text-muted-foreground">Link relevant products to show recommendations in the published article. The first selected product is primary.</p>
          <div className="grid max-h-48 gap-2 overflow-y-auto sm:grid-cols-2">
            {availableProducts.map((product) => (
              <label key={product.id} className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={productIds.includes(product.id)} onChange={(event) => setProductIds((ids) => event.target.checked ? [...ids, product.id] : ids.filter((id) => id !== product.id))} />
                {product.name}
              </label>
            ))}
          </div>
          {!availableProducts.length && <p className="text-sm text-muted-foreground">Create an affiliate product to attach it here.</p>}
        </fieldset>
        <details className="rounded-xl border p-4">
          <summary className="cursor-pointer font-semibold">
            SEO / Advanced SEO
          </summary>
          <p className="mt-3 text-sm text-muted-foreground">
            Leave overrides blank to use the post title, excerpt or plain-text
            content, featured image and generated public URL automatically.
          </p>
          <div className="mt-4 space-y-3">
            <label className="block text-sm">
              Meta title
              <input
                value={seoTitle}
                onChange={(e) => setSeoTitle(e.target.value)}
                maxLength={255}
                placeholder={title || "Automatic from title"}
                className="mt-2 w-full rounded-lg border p-3"
              />
            </label>
            <label className="block text-sm">
              Meta description
              <textarea
                value={seoDescription}
                onChange={(e) => setSeoDescription(e.target.value)}
                maxLength={500}
                rows={3}
                placeholder="Automatic from excerpt or content"
                className="mt-2 w-full rounded-lg border p-3"
              />
            </label>
            <label className="block text-sm">
              Canonical URL
              <input
                type="url"
                value={canonicalUrl}
                onChange={(e) => setCanonicalUrl(e.target.value)}
                placeholder="Automatically generated public URL"
                className="mt-2 w-full rounded-lg border p-3"
              />
            </label>
            <label className="block text-sm">
              Social image path
              <input
                value={socialImage}
                onChange={(e) => setSocialImage(e.target.value)}
                maxLength={2048}
                placeholder="Automatic from featured image"
                className="mt-2 w-full rounded-lg border p-3"
              />
            </label>
          </div>
        </details>

        {/* Allow Comments */}
        <div className="flex items-center gap-3 rounded-xl border p-4">
          <input
            id="allow_comments"
            type="checkbox"
            className="h-4 w-4 rounded border-gray-300"
            {...register("allow_comments")}
          />

          <label htmlFor="allow_comments" className="text-sm font-medium">
            Allow comments on this post
          </label>
        </div>

        {/* Submit */}
        <Button
          type="button"
          onClick={handleSave}
          className="w-full md:w-auto"
          disabled={mutation.isPending || isSubmitting}
        >
          {mutation.isPending || isSubmitting
            ? mode === "create"
              ? "Publishing..."
              : "Saving..."
            : mode === "create"
              ? "Publish Post"
              : "Save Changes"}
        </Button>
      </div>

      {/* ------------------------------------------------------------------ */}
      {/* Sidebar */}
      {/* ------------------------------------------------------------------ */}

      <PostEditorSidebar
        status={status}
        setStatus={(value) => {
          setValue("status", value, {
            shouldDirty: true,
            shouldTouch: true,
            shouldValidate: true,
          });
        }}
        featuredImage={featuredImage}
        setFeaturedImage={setFeaturedImage}
      />
    </form>
  );
}
