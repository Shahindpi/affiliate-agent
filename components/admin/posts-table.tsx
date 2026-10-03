"use client";
import { routes } from "@/lib/routes";

import Link from "next/link";
import Image from "next/image";

import PostRowActions from "./post-row-actions";

import { Badge } from "@/components/ui/badge";

import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

import { Post } from "@/types/post";
import { imageUrl } from "@/lib/image";

interface Props {
  posts: Post[];
}

export default function PostsTable({ posts }: Props) {
  if (!posts.length) {
    return (
      <div className="rounded-2xl border bg-background p-12 text-center">
        <p className="text-muted-foreground">No posts found.</p>
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-2xl border bg-background">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Image</TableHead>
            <TableHead>Title</TableHead>
            <TableHead>Category</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>Views</TableHead>
            <TableHead>Published</TableHead>
            <TableHead className="w-16 text-right">Actions</TableHead>
          </TableRow>
        </TableHeader>

        <TableBody>
          {posts.map((post) => (
            <TableRow key={post.id}>
              <TableCell>
                {post.featured_image ? (
                  <Image
                    unoptimized
                    width={64}
                    height={48}
                    src={imageUrl(post.featured_image)}
                    alt={`${post.title} thumbnail`}
                    className="h-12 w-16 rounded-lg border object-cover"
                  />
                ) : (
                  <div className="grid h-12 w-16 place-items-center rounded-lg bg-muted text-[10px] text-muted-foreground">
                    No image
                  </div>
                )}
              </TableCell>
              <TableCell>
                <div className="space-y-1">
                  <Link
                    href={routes.admin.posts.edit(post.id)}
                    className="font-medium hover:text-primary"
                  >
                    {post.title}
                  </Link>

                  <p className="text-xs text-muted-foreground truncate">
                    {post.slug}
                  </p>
                </div>
              </TableCell>

              <TableCell>{post.category?.name ?? "-"}</TableCell>

              <TableCell>
                <Badge
                  variant={
                    post.status === "published" ? "default" : "secondary"
                  }
                >
                  {post.status}
                </Badge>
              </TableCell>

              <TableCell>{post.views.toLocaleString()}</TableCell>

              <TableCell>
                {post.published_at
                  ? new Date(post.published_at).toLocaleDateString()
                  : "-"}
              </TableCell>

              <TableCell className="text-right">
                <PostRowActions
                  id={post.id}
                  slug={post.slug}
                  title={post.title}
                />
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}
