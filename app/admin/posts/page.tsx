"use client";
import { routes } from "@/lib/routes";

import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { Plus } from "lucide-react";

import PageTitle from "@/components/admin/page-title";
import PostSearch from "@/components/admin/post-search";
import PostStatusFilter from "@/components/admin/post-status-filter";
import PostsTable from "@/components/admin/posts-table";

import { useAppDispatch, useAppSelector } from "@/store/hooks";

import { getPosts } from "@/services/posts";
import { setPageSize } from "@/store/slices/postFiltersSlice";
import { PageSizeSelect } from "@/components/admin/page-size";
import TablePagination from "@/components/admin/table-pagination";
import { buttonVariants } from "@/components/ui/button";

export default function PostsPage() {
  const dispatch = useAppDispatch();
  const filters = useAppSelector((state) => state.postFilters);

  const { data: posts, isLoading } = useQuery({
    queryKey: ["posts", filters],
    queryFn: () => getPosts(filters),
  });

  return (
    <main className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <PageTitle
          title="Posts"
          description="Manage blog posts and AI tool reviews."
        />
        <Link href={routes.admin.posts.create} className={buttonVariants()}>
          <Plus className="mr-2 h-4 w-4" />
          New post
        </Link>
      </div>

      <div className="flex flex-col md:flex-row gap-4 justify-between">
        <PostSearch />

        <PostStatusFilter />
      </div>

      <PageSizeSelect value={filters.per_page} onChange={value => dispatch(setPageSize(value))} />
      {isLoading ? (
        <div className="h-80 rounded-xl bg-muted animate-pulse" />
      ) : (
        <>
          <PostsTable posts={posts?.posts ?? []} />
          {posts?.meta && (
            <TablePagination
              current={posts.meta.current_page}
              last={posts.meta.last_page}
            />
          )}
        </>
      )}
    </main>
  );
}
