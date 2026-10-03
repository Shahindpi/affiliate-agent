"use client";
import { routes } from "@/lib/routes";
import { useCallback, useEffect, useRef, useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { TrackedProductLink } from "@/components/public/tracked-product-link";
import { imageUrl } from "@/lib/image";
import { trackEvent, productParameters } from "@/lib/analytics";
import { recordAffiliateEvent } from "@/lib/affiliate-events";
import type { PublicProduct } from "@/lib/public-api";

export function AffiliateCarousel({ products }: { products: PublicProduct[] }) {
  const viewport = useRef<HTMLDivElement>(null);
  const seen = useRef(new Set<number>());
  const [index, setIndex] = useState(0);
  const [visible, setVisible] = useState(6);
  useEffect(() => {
    const update = () => setVisible(window.innerWidth >= 1024 ? 6 : window.innerWidth >= 768 ? 3 : window.innerWidth >= 640 ? 2 : 1);
    update(); window.addEventListener("resize", update);
    return () => window.removeEventListener("resize", update);
  }, []);
  const maxIndex = Math.max(0, products.length - visible);
  const scroll = useCallback((next: number) => {
    const target = Math.max(0, Math.min(maxIndex, next));
    setIndex(target);
    const item = viewport.current?.children.item(target) as HTMLElement | null;
    if (item && viewport.current) viewport.current.scrollTo({ left: item.offsetLeft - viewport.current.offsetLeft, behavior: "smooth" });
  }, [maxIndex]);
  useEffect(() => {
    const root = viewport.current;
    if (!root) return;
    const observer = new IntersectionObserver(entries => {
      for (const entry of entries) {
        if (!entry.isIntersecting || entry.intersectionRatio < 0.6) continue;
        const position = Number((entry.target as HTMLElement).dataset.position);
        const product = products[position];
        if (!product || seen.current.has(product.id)) continue;
        seen.current.add(product.id);
        recordAffiliateEvent(product, "impression", "homepage_popular");
        trackEvent("view_item_list", { ...productParameters(product, "homepage_popular"), position: position + 1 });
        trackEvent("affiliate_product_impression", { ...productParameters(product, "homepage_popular"), position: position + 1, carousel_name: "homepage_popular" });
      }
    }, { threshold: 0.6 });
    Array.from(root.children).forEach(element => observer.observe(element));
    return () => observer.disconnect();
  }, [products]);
  const currentIndex = Math.min(index, maxIndex);
  return <section aria-label="Popular Affiliate Products" className="mt-2">
    <div className="mb-5 flex flex-wrap items-center justify-between gap-3"><div><p className="text-sm font-bold uppercase tracking-widest text-[#2c9873]">Reader favorites</p><h2 className="text-3xl font-bold">Popular Affiliate Products</h2></div><div className="flex items-center gap-2"><Link href={routes.products.index} className="mr-2 text-sm font-semibold text-[#165e46] hover:underline">View all →</Link><button type="button" aria-label="Previous affiliate products" disabled={currentIndex === 0} onClick={() => scroll(currentIndex - 1)} className="rounded-lg border bg-white px-4 py-2 disabled:opacity-40">←</button><button type="button" aria-label="Next affiliate products" disabled={currentIndex >= maxIndex} onClick={() => scroll(currentIndex + 1)} className="rounded-lg border bg-white px-4 py-2 disabled:opacity-40">→</button></div></div>
    <div ref={viewport} className="relative flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth pb-3 [scrollbar-width:none]" onScroll={event => { const root = event.currentTarget; let nearest = 0; let distance = Infinity; Array.from(root.children).forEach((node, i) => { const delta = Math.abs((node as HTMLElement).offsetLeft - root.offsetLeft - root.scrollLeft); if (delta < distance) { distance = delta; nearest = i; } }); setIndex(Math.min(nearest, maxIndex)); }}>
      {products.map((product, position) => <article key={product.id} data-position={position} className="min-w-0 shrink-0 basis-full snap-start overflow-hidden rounded-xl border bg-white shadow-sm sm:basis-[calc((100%-0.75rem)/2)] md:basis-[calc((100%-1.5rem)/3)] lg:basis-[calc((100%-3.75rem)/6)]">
        <TrackedProductLink product={product} placement="homepage_popular" className="block">{product.featured_image ? <Image unoptimized src={imageUrl(product.featured_image)} alt={product.name} width={360} height={240} className="h-40 w-full" /> : <div className="grid h-40 place-items-center bg-[#eef4ec] text-sm">View product</div>}</TrackedProductLink>
        <div className="p-3"><p className="truncate text-xs text-[#2c9873]">{product.brand?.name || product.category?.name || "Product"}</p><TrackedProductLink product={product} placement="homepage_popular" className="mt-1 line-clamp-2 min-h-12 text-sm font-bold leading-6">{product.name}</TrackedProductLink>
        {/* {product.price != null && <p className="mt-2 text-sm font-semibold">{product.currency || "USD"} {product.price}</p>} */}
        {product.affiliate_url ? <a href={product.affiliate_url} target="_blank" rel="noopener noreferrer sponsored" onClick={() => { recordAffiliateEvent(product, "click", "homepage_popular"); trackEvent("affiliate_click", { ...productParameters(product, "homepage_popular"), destination: product.affiliate_url }); }} className="mt-3 block rounded-2xl bg-[var(--color-primary)] px-3 py-2 text-center text-xs font-bold text-white">View details ↗</a> : <TrackedProductLink product={product} placement="homepage_popular" className="mt-3 block text-sm font-bold text-[#165e46]">Details →</TrackedProductLink>}</div>
      </article>)}
    </div>{products.length === 0 && <p className="py-6 text-[#567069]">Products will appear here when published.</p>}
  </section>;
}
