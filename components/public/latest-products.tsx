"use client";
import { routes } from "@/lib/routes";
import { useEffect, useRef, useState } from "react";
import Image from "next/image";
import Link from "next/link";
import type { PublicProduct } from "@/lib/public-api";
import { imageUrl } from "@/lib/image";
import { AffiliateLink } from "@/components/public/affiliate-link";
import { TrackedProductLink } from "@/components/public/tracked-product-link";
import { productParameters, trackEvent } from "@/lib/analytics";
import { recordAffiliateEvent } from "@/lib/affiliate-events";

export function LatestProducts({ products }: { products: PublicProduct[] }) {
  const grid = useRef<HTMLDivElement>(null);
  const sentinel = useRef<HTMLDivElement>(null);
  const [items, setItems] = useState(products);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(false);
  const [ended, setEnded] = useState(products.length < 12);
  const pending = useRef(false);
  const count = useRef(products.length);
  const seen = useRef(new Set<number>());
  useEffect(() => {
    const root = grid.current;
    if (!root) return;
    const observer = new IntersectionObserver(entries => {
      for (const entry of entries) {
        if (!entry.isIntersecting || entry.intersectionRatio < 0.6) continue;
        const position = Number((entry.target as HTMLElement).dataset.position);
        const product = items[position];
        if (!product || seen.current.has(product.id)) continue;
        seen.current.add(product.id);
        recordAffiliateEvent(product, "impression", "homepage_latest");
        trackEvent("view_item_list", { ...productParameters(product, "homepage_latest"), position: position + 1 });
      }
    }, { threshold: 0.6 });
    Array.from(root.children).forEach(child => observer.observe(child));
    return () => observer.disconnect();
  }, [items]);
  async function loadMore() {
    if (pending.current || ended || count.current >= 21) return;
    pending.current = true;
    setLoading(true);
    setError(false);
    try {
      const base = (process.env.NEXT_PUBLIC_API_URL || "/api/v1").replace(/\/$/, "");
      const response = await fetch(`${base}/public/products?per_page=3&page=${Math.floor(count.current / 3) + 1}`, { headers: { Accept: "application/json" } });
      if (!response.ok) throw new Error(`Product request failed: ${response.status}`);
      const payload = await response.json() as { data: PublicProduct[]; meta?: { total?: number } };
      const batch = payload.data || [];
      setItems(previous => {
        const known = new Set(previous.map(product => product.id));
        return [...previous, ...batch.filter(product => !known.has(product.id))];
      });
      count.current += batch.length;
      if (batch.length < 3 || count.current >= (payload.meta?.total ?? Infinity)) setEnded(true);
    } catch { setError(true); }
    finally { pending.current = false; setLoading(false); }
  }
  useEffect(() => {
    const target = sentinel.current;
    if (!target || ended || error || loading || items.length >= 21) return;
    const observer = new IntersectionObserver(entries => {
      if (entries.some(entry => entry.isIntersecting)) void loadMore();
    }, { rootMargin: "0px 0px 200px 0px" });
    observer.observe(target);
    return () => observer.disconnect();
  // The observer is renewed after each batch; pending prevents duplicate requests.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [items.length, ended, error, loading]);
  return <section aria-label="Latest Affiliate Products" className="mt-2">
    <div className="mb-6 flex items-end justify-between gap-4"><div>
    <h1 className="mt-2 text-xl font-medium sm:text-2xl uppercase text-[var(--dewdora-green)]">Affiliate Products</h1></div><Link href={routes.products.index} className="shrink-0 font-semibold text-[#165e46] hover:underline">View all →</Link></div>
    <div ref={grid} className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
      {items.map((product, position) => <article key={product.id} data-position={position} className="overflow-hidden rounded-2xl border border-[#dce6d9] bg-white">
        <TrackedProductLink product={product} placement="homepage_latest" className="block bg-[#eef4ec]"><div className="relative h-56 sm:h-56">{product.featured_image ? <Image unoptimized fill sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw" src={imageUrl(product.featured_image)} alt={product.name} className="rounded-tr-lg" /> : <span className="grid h-full place-items-center">View product</span>}</div></TrackedProductLink>
        <div className="p-5"><p className="text-xs font-bold uppercase tracking-wide text-[#2c9873]">{product.brand?.name || product.category?.name || "Product"}</p><TrackedProductLink product={product} placement="homepage_latest" className="mt-2 block min-h-14 text-lg font-bold hover:text-[#2c9873]">{product.name}</TrackedProductLink><p className="mt-2 line-clamp-2 min-h-10 text-sm text-[#567069]">{product.short_description}</p><div className="mt-5 flex items-center justify-between gap-3">
          {/* {product.price != null && <strong>{product.currency || "USD"} {product.price}</strong>} */}
          {product.affiliate_url ? <AffiliateLink product={product} placement="homepage_latest" className="rounded-2xl bg-[var(--color-primary)] px-4 py-2 text-sm font-bold text-white hover:bg-[#0e4634]">View details ↗</AffiliateLink> : <TrackedProductLink product={product} placement="homepage_latest" className="font-bold text-[#165e46]">Details →</TrackedProductLink>}</div><p className="mt-3 text-xs text-[#567069]">Affiliate link: we may earn a commission.</p></div>
      </article>)}
    </div>{items.length === 0 && <p className="py-8 text-[#567069]">New products will appear here when published</p>}
    <div ref={sentinel} aria-hidden="true" className="h-px" />
    {loading && <div role="status" className="mt-5 grid animate-pulse gap-5 sm:grid-cols-2 lg:grid-cols-3">{[0, 1, 2].map(index => <div key={index} className="h-72 rounded-2xl bg-[#e4ece2]" />)}<span className="sr-only">Loading more products</span></div>}
    {error && <p role="alert" className="mt-5">Could not load more products. <button type="button" onClick={() => void loadMore()} className="cursor-pointer font-bold underline">Retry</button></p>}
    {items.length >= 21 && <p className="mt-6 text-center text-[#567069]">Want to explore more? <Link href={routes.products.index} className="font-bold text-[#165e46] underline">View all products</Link></p>}
  </section>;
}
