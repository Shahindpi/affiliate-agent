import api from "@/lib/axios";

import { ApiResponse } from "@/types/api";
import { Category } from "@/types/category";

export async function getCategories() {
  const response = await api.get<ApiResponse<Category[]>>(
    "/public/categories"
  );

  return response.data.data;
}