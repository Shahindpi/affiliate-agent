import type { Metadata } from "next";
import { imageUrl } from "@/lib/image";
import type { PublicPost, PublicProduct } from "@/lib/public-api";

export const siteOrigin = (process.env.NEXT_PUBLIC_SITE_URL || "http://localhost:3000").replace(/\/$/, "");
export const defaultImage = `${siteOrigin}/dewdora-og.png`;
const defaultDescription = "Discover useful products, independent reviews and practical buying guides from Dewdora.";
export function absoluteImage(input?: string | null) {
  if (!input) return defaultImage;
  if (/^https?:\/\//i.test(input)) return input;
  if (input.startsWith("/storage/")) return imageUrl(input.slice(9));
  if (input.startsWith("/uploads/")) return imageUrl(input.slice(1));
  return input.startsWith("/") ? `${siteOrigin}${input}` : imageUrl(input);
}
export function pageMetadata(path: string, title: string, description = defaultDescription, image?: string | null, type: "website" | "article" = "website", canonical?: string | null): Metadata {
  const url = new URL(path, `${siteOrigin}/`).toString();
  const proposed = canonical ? new URL(canonical, `${siteOrigin}/`) : null;
  const canonicalUrl = proposed && proposed.origin === siteOrigin ? proposed.toString() : url;
  const imageUrl = absoluteImage(image);
  return {
    title,
    description,
    alternates: { canonical: canonicalUrl },
    openGraph: { title, description, url: canonicalUrl, siteName: "Dewdora", type, images: [{ url: imageUrl, ...(imageUrl === defaultImage ? { width: 1200, height: 630 } : {}), alt: title }] },
    twitter: { card: "summary_large_image", title, description, images: [imageUrl] },
  };
}
export function productMetadata(product: PublicProduct): Metadata {
  const seo = product.seo;
  const description = strip(seo?.meta_description || product.short_description || product.description || "Explore this product on Dewdora.");
  return pageMetadata(`/products/${encodeURIComponent(product.slug)}`, seo?.meta_title || product.name, description, seo?.open_graph?.image || product.featured_image || product.brand?.logo, "website", seo?.canonical_url);
}
export function postMetadata(post: PublicPost): Metadata {
  const seo = post.seo;
  const description = strip(seo?.meta_description || post.excerpt || post.content || "Read this article on Dewdora.");
  return pageMetadata(`/posts/${encodeURIComponent(post.slug)}`, seo?.meta_title || post.title, description, seo?.open_graph?.image || post.featured_image, "article", seo?.canonical_url);
}
function strip(value: string) { return value.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim().slice(0, 220); }
export function JsonLd({ data }: { data: Record<string, unknown> }) {
  return <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(data).replace(/</g, "\\u003c") }} />;
}
export function breadcrumbs(parts: { name: string; path: string }[]) {
  return { "@context": "https://schema.org", "@type": "BreadcrumbList", itemListElement: parts.map((part, index) => ({ "@type": "ListItem", position: index + 1, name: part.name, item: `${siteOrigin}${part.path}` })) };
}
