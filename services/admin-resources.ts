import api from "@/lib/axios";
import type { ApiResponse, PaginationMeta } from "@/types/api";

export type ResourceRecord = { id: number; name?: string; slug?: string; [key: string]: unknown };
export type ResourceEndpoint = "categories" | "tags" | "brands" | "affiliate-networks" | "roles";

export async function allResourceOptions<T>(endpoint: ResourceEndpoint | "affiliate-products"): Promise<T[]> {
  const items: T[] = [];
  let page = 1;
  let lastPage = 1;
  do {
    const { data } = await api.get<ApiResponse<T[]>>(`/admin/${endpoint}`, { params: { page, per_page: 100 } });
    if (!Array.isArray(data.data)) throw new Error("The server returned invalid options.");
    items.push(...data.data);
    lastPage = data.meta?.last_page || 1;
    page++;
  } while (page <= lastPage);
  return items;
}

export async function listResources(endpoint: ResourceEndpoint, params: { page: number; search: string; per_page?: number | "all" }) {
  const { data } = await api.get<ApiResponse<ResourceRecord[]>>(`/admin/${endpoint}`, { params });
  if (!Array.isArray(data.data)) throw new Error("The server returned an invalid resource list.");
  return { items: data.data, meta: data.meta as PaginationMeta | undefined };
}

export async function saveResource(endpoint: ResourceEndpoint, values: Record<string, unknown>, id?: number) {
  const response = id
    ? await api.put<ApiResponse<ResourceRecord>>(`/admin/${endpoint}/${id}`, values)
    : await api.post<ApiResponse<ResourceRecord>>(`/admin/${endpoint}`, values);
  return response.data.data;
}

export async function deleteResource(endpoint: ResourceEndpoint, id: number) {
  await api.delete(`/admin/${endpoint}/${id}`);
}
