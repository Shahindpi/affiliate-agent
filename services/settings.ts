import api from "@/lib/axios";

import { ApiResponse } from "@/types/api";
import { SiteSettings } from "@/types/setting";

export async function getSettings() {
  const response = await api.get<ApiResponse<SiteSettings>>(
    "/public/settings"
  );

  return response.data.data;
}

export async function getHomepage() {
  const response = await api.get("/public/home");

  return response.data.data;
}