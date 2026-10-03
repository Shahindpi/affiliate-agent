import PageTitle from "@/components/admin/page-title";
import PostForm from "@/components/admin/posts/post-form";

export default function NewPostPage() {
  return (
    <main className="space-y-6">
      <PageTitle
        title="Create Post"
        description="Write a new article, review, or tutorial."
      />

      <PostForm mode="create" />
    </main>
  );
}