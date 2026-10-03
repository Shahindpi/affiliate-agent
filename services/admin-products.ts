import api from "@/lib/axios";
import type { ApiResponse, PaginationMeta } from "@/types/api";
import type { AffiliateProduct, AffiliateNetwork, Brand } from "@/types/product";
import type { Category } from "@/types/category";
import { allResourceOptions } from "@/services/admin-resources";

export interface ProductPayload {
  name: string;
  slug: string | null;
  brand_id: number | null;
  affiliate_network_id: number | null;
  category_id: number | null;
  short_description: string | null;
  description: string | null;
  website_url: string | null;
  affiliate_url: string;
  price: number | null;
  currency: string | null;
  commission_rate: number | null;
  free_trial: boolean;
  rating: number | null;
  featured_image: string | null;
  pros: string[];
  cons: string[];
  featured: boolean;
  status: boolean;
}

export async function getAdminProducts(params?: Record<string, string | number | boolean | undefined>) {
  const response = await api.get<ApiResponse<AffiliateProduct[]>>("/admin/affiliate-products", { params });
  return { products: response.data.data, meta: response.data.meta as PaginationMeta };
}

export async function getAdminProduct(id: number) {
  const response = await api.get<ApiResponse<AffiliateProduct>>(`/admin/affiliate-products/${id}`);
  return response.data.data;
}

export async function createAdminProduct(payload: ProductPayload) {
  const response = await api.post<ApiResponse<AffiliateProduct>>("/admin/affiliate-products", payload);
  return response.data.data;
}

export async function updateAdminProduct(id: number, payload: ProductPayload) {
  const response = await api.put<ApiResponse<AffiliateProduct>>(`/admin/affiliate-products/${id}`, payload);
  return response.data.data;
}

export async function deleteAdminProduct(id: number) {
  await api.delete(`/admin/affiliate-products/${id}`);
}

export async function getProductOptions() {
  const [brands, networks, categories] = await Promise.all([
    allResourceOptions<Brand>("brands"),
    allResourceOptions<AffiliateNetwork>("affiliate-networks"),
    allResourceOptions<Category>("categories"),
  ]);
  return { brands, networks, categories };
}
