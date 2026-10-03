"use client";
import type { ReactNode } from "react";
import { trackEvent, productParameters } from "@/lib/analytics";
import { recordAffiliateEvent } from "@/lib/affiliate-events";
import type { PublicProduct } from "@/lib/public-api";
export function AffiliateLink({ product, placement, className, children }: { product: PublicProduct; placement: string; className?: string; children: ReactNode }) {
  if (!product.affiliate_url) return null;
  return <a href={product.affiliate_url} target="_blank" rel="noopener noreferrer sponsored" className={className} onClick={() => { recordAffiliateEvent(product, "click", placement); trackEvent("affiliate_click", { ...productParameters(product, placement), destination: product.affiliate_url }); }}>{children}</a>;
}
