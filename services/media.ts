import api from "@/lib/axios";
import type { MediaItem } from "@/types/media";
export async function getMedia(): Promise<MediaItem[]> {
  const response = await api.get("/admin/media");
  return response.data.data?.media || [];
}
export async function uploadMedia(file: File): Promise<MediaItem> {
  const formData = new FormData(); formData.append("image", file);
  const response = await api.post("/admin/media/upload", formData, { headers: { "Content-Type": "multipart/form-data" } });
  return response.data.data;
}
