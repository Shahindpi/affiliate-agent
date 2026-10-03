import api from "@/lib/axios";

import { AffiliateProduct } from "@/types/product";
import { ApiResponse, PaginatedResponse } from "@/types/api";

export async function getProducts(params?: {
  page?: number;
  brand?: string;
  category?: string;
}) {
  const response = await api.get<PaginatedResponse<AffiliateProduct>>(
    "/public/products",
    {
      params,
    }
  );

  return response.data;
}

export async function getProduct(slug: string) {
  const response = await api.get<ApiResponse<{ product: AffiliateProduct }>>(
    `/public/products/${slug}`
  );

  return response.data.data.product;
}