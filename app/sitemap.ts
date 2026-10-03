import type { MetadataRoute } from "next";
import { publicGet, type ListResult } from "@/lib/public-api";
import { siteOrigin } from "@/lib/seo";

export const revalidate = 3600;
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const staticPaths = ["/", "/products", "/posts", "/categories", "/brands", "/tags", "/contact"];
  const entries: MetadataRoute.Sitemap = staticPaths.map(path => ({ url: `${siteOrigin}${path}`, changeFrequency: "weekly" }));
  for (const kind of ["products", "posts", "categories", "brands"] as const) {
    let page = 1;
    let last = 1;
    do {
      const result = await publicGet<ListResult<{ slug: string; updated_at?: string }>>(kind, { page, per_page: 50 });
      entries.push(...result.data.map(item => ({ url: `${siteOrigin}/${kind}/${encodeURIComponent(item.slug)}`, ...(item.updated_at ? { lastModified: new Date(item.updated_at) } : {}) })));
      last = result.meta?.last_page || 1;
      page++;
    } while (page <= last);
  }
  return entries;
}
