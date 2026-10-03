import type { PublicProduct } from "@/lib/public-api";
export type AnalyticsParameters = Record<string, string | number | undefined | null>;
declare global { interface Window { gtag?: (...args: unknown[]) => void; dataLayer?: unknown[]; } }
export function trackEvent(name: string, parameters: AnalyticsParameters) {
  const payload = Object.fromEntries(Object.entries(parameters).filter(([, value]) => value !== undefined && value !== null && value !== ""));
  if (process.env.NODE_ENV === "development") console.info("[Dewdora analytics]", name, payload);
  if (!process.env.NEXT_PUBLIC_GA_ID || typeof window === "undefined") return;
  if (window.gtag) window.gtag("event", name, payload);
  else (window.dataLayer ||= []).push(["event", name, payload]);
}

export function productParameters(product: PublicProduct, placement: string): AnalyticsParameters {
  return {
    product_id: product.id, product_name: product.name, product_slug: product.slug,
    brand_id: product.brand_id, brand_name: product.brand?.name,
    category_id: product.category_id, category_name: product.category?.name,
    network_id: product.affiliate_network_id, network_name: product.affiliate_network?.name,
    placement,
  };
}
