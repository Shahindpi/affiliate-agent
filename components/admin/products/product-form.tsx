"use client";
import { routes } from "@/lib/routes";

import { useEffect, useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { useQueryClient } from "@tanstack/react-query";
import Image from "next/image";
import { toast } from "sonner";
import MediaPickerModal from "@/components/admin/media/media-picker-modal";
import RichTextEditor from "@/components/admin/editor/rich-text-editor";
import { apiErrorMessage } from "@/lib/api-error";
import { imageUrl } from "@/lib/image";
import {
  createAdminProduct,
  getProductOptions,
  updateAdminProduct,
} from "@/services/admin-products";
import type {
  AffiliateNetwork,
  AffiliateProduct,
  Brand,
} from "@/types/product";
import type { Category } from "@/types/category";

export function ProductForm({ product }: { product?: AffiliateProduct }) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const [brands, setBrands] = useState<Brand[]>([]);
  const [networks, setNetworks] = useState<AffiliateNetwork[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [description, setDescription] = useState(product?.description || "");
  const [imagePath, setImagePath] = useState(
    product?.featured_image_path || null,
  );
  const [imagePreview, setImagePreview] = useState(
    product?.featured_image || null,
  );
  const [mediaOpen, setMediaOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [brandId, setBrandId] = useState(String(product?.brand_id || ""));
  const [networkId, setNetworkId] = useState(String(product?.affiliate_network_id || ""));
  const [categoryId, setCategoryId] = useState(String(product?.category_id || ""));

  useEffect(() => {
    getProductOptions()
      .then((options) => {
        setBrands(options.brands);
        setNetworks(options.networks);
        setCategories(options.categories);
      })
      .catch((error) =>
        toast.error(apiErrorMessage(error, "Could not load product options.")),
      );
  }, []);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    const data = new FormData(event.currentTarget);
    const numberOrNull = (key: string) =>
      data.get(key) ? Number(data.get(key)) : null;
    const lines = (key: string) =>
      String(data.get(key) || "")
        .split("\n")
        .map((value) => value.trim())
        .filter(Boolean);
    const payload = {
      name: String(data.get("name") || ""),
      slug: String(data.get("slug") || "") || null,
      brand_id: numberOrNull("brand_id"),
      affiliate_network_id: numberOrNull("affiliate_network_id"),
      category_id: numberOrNull("category_id"),
      short_description: String(data.get("short_description") || "") || null,
      description: description || null,
      website_url: String(data.get("website_url") || "") || null,
      affiliate_url: String(data.get("affiliate_url") || ""),
      price: numberOrNull("price"),
      currency: String(data.get("currency") || "USD") || null,
      rating: numberOrNull("rating"),
      commission_rate: numberOrNull("commission_rate"),
      featured_image: imagePath,
      pros: lines("pros"),
      cons: lines("cons"),
      free_trial: data.has("free_trial"),
      featured: data.has("featured"),
      status: data.has("status"),
    };
    try {
      if (product) await updateAdminProduct(product.id, payload);
      else await createAdminProduct(payload);
      await queryClient.invalidateQueries({ queryKey: ["admin-products"] });
      await queryClient.invalidateQueries({ queryKey: ["admin-product"] });
      toast.success(product ? "Product updated." : "Product created.");
      router.push(routes.admin.products.index);
      router.refresh();
    } catch (error) {
      toast.error(apiErrorMessage(error, "Could not save product."));
    } finally {
      setBusy(false);
    }
  }

  const input = "mt-2 w-full rounded-lg border bg-background p-3";
  return (
    <>
      <form
        onSubmit={submit}
        className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]"
      >
        <div className="space-y-6 rounded-2xl border bg-background p-6">
          <div className="grid gap-5 md:grid-cols-2">
            <label className="text-sm font-medium">
              Product name
              <input
                name="name"
                required
                defaultValue={product?.name}
                className={input}
              />
            </label>
            <label className="text-sm font-medium">
              Slug
              <input
                name="slug"
                defaultValue={product?.slug}
                className={input}
              />
            </label>
            <label className="text-sm font-medium">
              Brand
              <select
                name="brand_id"
                value={brandId}
                onChange={event => setBrandId(event.target.value)}
                className={input}
              >
                <option value="">No brand</option>
                {brands.map((brand) => (
                  <option key={brand.id} value={brand.id}>
                    {brand.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="text-sm font-medium">
              Affiliate network
              <select
                name="affiliate_network_id"
                value={networkId}
                onChange={event => setNetworkId(event.target.value)}
                className={input}
              >
                <option value="">No network</option>
                {networks.map((network) => (
                  <option key={network.id} value={network.id}>
                    {network.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="text-sm font-medium">
              Category
              <select
                name="category_id"
                value={categoryId}
                onChange={event => setCategoryId(event.target.value)}
                className={input}
              >
                <option value="">No category</option>
                {categories.map((category) => (
                  <option key={category.id} value={category.id}>
                    {category.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="text-sm font-medium">
              Affiliate URL
              <input
                name="affiliate_url"
                type="url"
                required
                defaultValue={product?.affiliate_url}
                className={input}
              />
            </label>
            <label className="text-sm font-medium">
              Website URL
              <input
                name="website_url"
                type="url"
                defaultValue={product?.website_url || ""}
                className={input}
              />
            </label>
            <label className="text-sm font-medium">
              Price
              <input
                name="price"
                type="number"
                min="0"
                step="0.01"
                defaultValue={product?.price ?? ""}
                className={input}
              />
            </label>
            <label className="text-sm font-medium">
              Currency
              <input
                name="currency"
                maxLength={3}
                defaultValue={product?.currency || "USD"}
                className={input}
              />
            </label>
            <label className="text-sm font-medium">
              Rating (0–5)
              <input
                name="rating"
                type="number"
                min="0"
                max="5"
                step="0.1"
                defaultValue={product?.rating ?? ""}
                className={input}
              />
            </label>
            <label className="text-sm font-medium">
              Commission rate (%)
              <input
                name="commission_rate"
                type="number"
                min="0"
                max="100"
                step="0.01"
                defaultValue={product?.commission_rate ?? ""}
                className={input}
              />
            </label>
          </div>
          <label className="block text-sm font-medium">
            Short description
            <textarea
              name="short_description"
              rows={3}
              defaultValue={product?.short_description || ""}
              className={input}
            />
          </label>
          <div>
            <p className="mb-2 text-sm font-medium">Full description</p>
            <RichTextEditor
              value={description}
              onChange={setDescription}
              imageAltFallback={product?.name || "Product image"}
            />
          </div>
          <div className="grid gap-5 md:grid-cols-2">
            <label className="text-sm font-medium">
              Pros (one per line)
              <textarea
                name="pros"
                rows={5}
                defaultValue={product?.pros?.join("\n") || ""}
                className={input}
              />
            </label>
            <label className="text-sm font-medium">
              Cons (one per line)
              <textarea
                name="cons"
                rows={5}
                defaultValue={product?.cons?.join("\n") || ""}
                className={input}
              />
            </label>
          </div>
        </div>
        <aside className="space-y-6">
          <div className="rounded-2xl border bg-background p-5">
            <h2 className="font-semibold">Product image</h2>
            {imagePreview ? (
              <Image
                unoptimized
                width={640}
                height={384}
                src={imagePreview}
                alt="Selected product"
                className="mt-4 h-48 w-full rounded-xl border object-contain"
              />
            ) : (
              <div className="mt-4 flex h-48 items-center justify-center rounded-xl border border-dashed text-sm text-muted-foreground">
                No image selected
              </div>
            )}
            <button
              type="button"
              onClick={() => setMediaOpen(true)}
              className="mt-4 w-full rounded-lg border px-4 py-2"
            >
              Choose from media
            </button>
            {imagePath && (
              <button
                type="button"
                onClick={() => {
                  setImagePath(null);
                  setImagePreview(null);
                }}
                className="mt-2 w-full text-sm text-destructive"
              >
                Remove image
              </button>
            )}
          </div>
          <div className="space-y-4 rounded-2xl border bg-background p-5">
            <label className="flex items-center gap-3">
              <input
                type="checkbox"
                name="status"
                defaultChecked={product?.status ?? true}
              />{" "}
              Active
            </label>
            <label className="flex items-center gap-3">
              <input
                type="checkbox"
                name="featured"
                defaultChecked={product?.featured ?? false}
              />{" "}
              Featured
            </label>
            <label className="flex items-center gap-3">
              <input
                type="checkbox"
                name="free_trial"
                defaultChecked={product?.free_trial ?? false}
              />{" "}
              Free trial
            </label>
            <button
              disabled={busy}
              className="w-full rounded-lg bg-primary px-5 py-3 text-primary-foreground disabled:opacity-50"
            >
              {busy ? "Saving…" : "Save product"}
            </button>
            <button
              type="button"
              onClick={() => router.push(routes.admin.products.index)}
              className="w-full rounded-lg border px-5 py-3"
            >
              Cancel
            </button>
          </div>
        </aside>
      </form>
      <MediaPickerModal
        open={mediaOpen}
        onClose={() => setMediaOpen(false)}
        onSelect={(media) => {
          setImagePath(media.path);
          setImagePreview(imageUrl(media.path));
          setMediaOpen(false);
        }}
      />
    </>
  );
}
