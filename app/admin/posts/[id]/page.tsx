"use client";

import { use } from "react";
import { useQuery } from "@tanstack/react-query";

import PageTitle from "@/components/admin/page-title";
import PostForm from "@/components/admin/posts/post-form";

import { getPost } from "@/services/posts";

interface Props {
  params: Promise<{
    id: string;
  }>;
}

export default function EditPostPage({
  params,
}: Props) {
  const { id } = use(params);

  const { data, isLoading } = useQuery({
    queryKey: ["post", id],
    queryFn: () => getPost(Number(id)),
  });

  if (isLoading || !data) {
    return (
      <main className="space-y-6">
        <PageTitle
          title="Edit Post"
          description="Loading article..."
        />

        <div className="h-96 rounded-xl bg-muted animate-pulse" />
      </main>
    );
  }

  return (
    <main className="space-y-6">
      <PageTitle
        title="Edit Post"
        description="Update your article."
      />

      <PostForm
        mode="edit"
        post={data}
      />
    </main>
  );
}