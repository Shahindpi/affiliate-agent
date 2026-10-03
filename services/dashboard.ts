import api from "@/lib/axios";

import { ApiResponse } from "@/types/api";
import {
  DashboardOverview,
  DashboardAnalytics,
} from "@/types/dashboard";

export async function getDashboardOverview() {
  const response = await api.get<ApiResponse<DashboardOverview>>(
    "/admin/dashboard/overview"
  );

  return response.data.data;
}

export async function getDashboardAnalytics() {
  const response = await api.get<ApiResponse<DashboardAnalytics>>(
    "/admin/dashboard/analytics"
  );

  return response.data.data;
}
export async function getDashboardPopularPosts() {
  const response = await api.get<ApiResponse<import("@/types/dashboard").PopularPost[]>>("/admin/dashboard/popular-posts");
  return response.data.data;
}
