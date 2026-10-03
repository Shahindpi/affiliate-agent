const STORAGE_URL = process.env.NEXT_PUBLIC_STORAGE_URL || "http://127.0.0.1:8000/storage";

export function imageUrl(path: string | null) {
  if (!path) return "/placeholder.png";

  if (path.startsWith("http")) {
    return path;
  }

  return `${STORAGE_URL}/${path.replace(/^\/+/, "")}`;
}