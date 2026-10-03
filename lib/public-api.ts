import type { Post } from "@/types/post";
import type { AffiliateProduct } from "@/types/product";
import type { Category } from "@/types/category";
import type { ApiResponse, PaginationMeta } from "@/types/api";

export type PublicPost = Post & { content?: string; reading_time?: number; allow_comments?: boolean; author?: { id: number; name: string } | null; seo?: { meta_title?: string; meta_description?: string; canonical_url?: string; open_graph?: { image?: string }; overrides?: { meta_title?: string | null; meta_description?: string | null; canonical_url?: string | null }; has_overrides?: boolean }; affiliate_products?: PublicProduct[] };
export type PublicProduct = AffiliateProduct & { description?: string; affiliate_url?: string; website_url?: string; pros?: string[]; cons?: string[]; free_trial?: boolean; seo?: { meta_title?: string; meta_description?: string; canonical_url?: string; open_graph?: { image?: string } } };
export type PublicCategory = Category & { description?: string; posts_count?: number };
export type ListResult<T> = { data: T[]; meta?: PaginationMeta };

export class PublicApiError extends Error {
  constructor(public readonly status: number) {
    super(`Public API returned ${status}`);
  }
}

// Laravel returns resource collections directly, but detail endpoints use ApiResponse.
export async function publicGet<T>(path: string, query?: Record<string, string | number | undefined>): Promise<T> {
  const base = (process.env.API_URL || process.env.NEXT_PUBLIC_API_URL || "http://127.0.0.1:8000/api/v1").replace(/\/$/, "");
  const url = new URL(`${base}/public/${path.replace(/^\//, "")}`);
  Object.entries(query || {}).forEach(([key, value]) => { if (value !== undefined && value !== "") url.searchParams.set(key, String(value)); });
  const response = await fetch(url, { cache: "no-store", headers: { Accept: "application/json" } });
  if (!response.ok) throw new PublicApiError(response.status);
  return (await response.json()) as T;
}

export async function safePublicGet<T>(path: string, fallback: T, query?: Record<string, string | number | undefined>): Promise<T> {
  try { return await publicGet<T>(path, query); }
  catch (error) {
    if (error instanceof PublicApiError && error.status === 404) return fallback;
    throw error;
  }
}

export type Detail<T, K extends string> = ApiResponse<Record<K, T>>;
