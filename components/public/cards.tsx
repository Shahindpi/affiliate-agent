"use client";
import { routes } from "@/lib/routes";
import { useEffect, useRef } from "react";
import { AffiliateLink } from "@/components/public/affiliate-link";
import { TrackedProductLink } from "@/components/public/tracked-product-link";
import { productParameters, trackEvent } from "@/lib/analytics";
import { recordAffiliateEvent } from "@/lib/affiliate-events";
import Link from "next/link";
import Image from "next/image";
import { imageUrl } from "@/lib/image";
import type { PublicPost, PublicProduct } from "@/lib/public-api";

export function PostCard({ post }: { post: PublicPost }) {
  return <Link href={routes.posts.show(post.slug)} className="group overflow-hidden rounded-2xl border border-[#dce6d9] bg-white transition hover:-translate-y-1 hover:shadow-lg">
    {post.featured_image && <Image unoptimized width={720} height={384} src={imageUrl(post.featured_image)} alt={`${post.title} featured image`} className="h-48 w-full" />}
    <div className="p-6"><p className="text-xs font-bold uppercase tracking-widest text-[#2c9873]">{post.category?.name || "Article"}</p><h3 className="mt-2 text-xl font-bold group-hover:text-[#2c9873]">{post.title}</h3><p className="mt-3 line-clamp-3 text-sm text-[#567069]">{post.excerpt}</p></div>
  </Link>;
}
export function ProductCard({ product, placement = "product_archive" }: { product: PublicProduct; placement?: string }) {
  const card = useRef<HTMLElement>(null);
  useEffect(() => {
    const node = card.current;
    if (!node) return;
    const observer = new IntersectionObserver(entries => {
      if (entries.some(entry => entry.isIntersecting && entry.intersectionRatio >= 0.5)) {
        recordAffiliateEvent(product, "impression", placement);
        trackEvent("view_item_list", productParameters(product, placement));
        observer.disconnect();
      }
    }, { threshold: 0.5 });
    observer.observe(node);
    return () => observer.disconnect();
  }, [product, placement]);
  return <article ref={card} className="group overflow-hidden rounded-2xl border border-[#dce6d9] bg-white transition hover:-translate-y-1 hover:shadow-lg">
    <TrackedProductLink product={product} placement={placement}>{product.featured_image && <Image unoptimized width={720} height={384} src={imageUrl(product.featured_image)} alt={`${product.name} product`} className="h-52 w-full bg-[#eef4ec]" />}</TrackedProductLink>
    <div className="p-6"><p className="text-xs font-bold uppercase tracking-widest text-[#2c9873]">{product.brand?.name || product.category?.name || "Recommended"}</p><TrackedProductLink product={product} placement={placement}><h3 className="mt-2 text-xl font-bold group-hover:text-[#2c9873]">{product.name}</h3></TrackedProductLink><p className="mt-3 line-clamp-3 text-sm text-[#567069]">{product.short_description}</p><div className="mt-5 flex items-center justify-between gap-3">
      {/* {product.price != null ? <span className="font-bold">{product.currency || "USD"} {product.price}</span> : <span />} */}
      {product.affiliate_url ? <AffiliateLink product={product} placement={placement} className="rounded-2xl bg-[var(--color-primary)] px-4 py-2 text-sm font-bold text-white">View details ↗</AffiliateLink> : <TrackedProductLink product={product} placement={placement} className="text-sm font-bold text-[#165e46]">View product →</TrackedProductLink>}</div></div>
  </article>;
}
