import api from "@/lib/axios";

import { ApiResponse, PaginationMeta } from "@/types/api";
import { Post } from "@/types/post";

import type { PostFormValues } from "@/schemas/post-schema";

interface PostFilters {
  page?: number;
  per_page?: number | "all";
  search?: string;
  status?: string;
  category_id?: number;
}

/*
|--------------------------------------------------------------------------
| Payload sent to Laravel
|--------------------------------------------------------------------------
*/

export type PostPayload = Omit<PostFormValues, "category_id"> & {
  category_id: number | null;
  featured_image?: string | null;
};

/*
|--------------------------------------------------------------------------
| Get Posts
|--------------------------------------------------------------------------
*/

export async function getPosts(filters: PostFilters) {
  const response = await api.get<ApiResponse<Post[]>>("/admin/posts", {
    params: filters,
  });

  return {
    posts: response.data.data,
    meta: response.data.meta as PaginationMeta,
  };
}

/*
|--------------------------------------------------------------------------
| Single Post
|--------------------------------------------------------------------------
*/

export async function getPost(id: number) {
  const response = await api.get(`/admin/posts/${id}`);
  return response.data.data;
}

/*
|--------------------------------------------------------------------------
| Create
|--------------------------------------------------------------------------
*/

export async function createPost(payload: PostPayload) {
  const response = await api.post("/admin/posts", payload);
  return response.data.data;
}

/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/

export async function updatePost(
  id: number,
  payload: PostPayload
) {
  const response = await api.put(`/admin/posts/${id}`, payload);
  return response.data.data;
}

/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/

export async function deletePost(id: number) {
  const response = await api.delete(`/admin/posts/${id}`);
  return response.data;
}
