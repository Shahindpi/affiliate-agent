import api from "@/lib/axios";
import type { ApiResponse } from "@/types/api";

export type CmsPage = { id: number; legal_key: string | null; title: string; slug: string; excerpt: string | null; content: string; status: boolean; updated_at: string; seo_meta?: { meta_title: string | null; meta_description: string | null } | null };
export type CmsPagePayload = { title: string; slug: string; excerpt: string | null; content: string; status: boolean; seo: { meta_title: string | null; meta_description: string | null } };
export const listAdminPages = async () => (await api.get<ApiResponse<{ data: CmsPage[]; total: number }>>("/admin/pages")).data.data;
export const getAdminPage = async (id: number) => (await api.get<ApiResponse<CmsPage>>(`/admin/pages/${id}`)).data.data;
export const saveAdminPage = async (payload: CmsPagePayload, id?: number) => (await (id ? api.put<ApiResponse<CmsPage>>(`/admin/pages/${id}`, payload) : api.post<ApiResponse<CmsPage>>("/admin/pages", payload))).data.data;
