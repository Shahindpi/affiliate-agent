import type { PublicProduct } from "@/lib/public-api";

export function recordAffiliateEvent(product: PublicProduct, kind: "impression" | "click", placement: string) {
  try {
    const storageKey = "dewdora_visitor_session";
    let session = sessionStorage.getItem(storageKey);
    if (!session) { session = crypto.randomUUID(); sessionStorage.setItem(storageKey, session); }
    if (kind === "impression") {
      const seenKey = "dewdora_seen_products";
      const seen: number[] = JSON.parse(sessionStorage.getItem(seenKey) || "[]");
      if (seen.includes(product.id)) return;
      sessionStorage.setItem(seenKey, JSON.stringify([...seen, product.id]));
    }
    const base = (process.env.NEXT_PUBLIC_API_URL || "/api/v1").replace(/\/$/, "");
    const body = JSON.stringify({ affiliate_product_id: product.id, kind, session_id: session, placement });
    const url = `${base}/public/affiliate-events`;
    if (!navigator.sendBeacon?.(url, new Blob([body], { type: "application/json" }))) {
      void fetch(url, { method: "POST", headers: { "Content-Type": "application/json" }, body, keepalive: true }).catch(() => {});
    }
  } catch { /* Analytics must never prevent navigation. */ }
}
