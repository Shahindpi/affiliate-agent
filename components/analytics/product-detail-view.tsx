"use client";
import { useEffect } from "react";
import { productParameters, trackEvent } from "@/lib/analytics";
import type { PublicProduct } from "@/lib/public-api";

export function ProductDetailView({ product }: { product: PublicProduct }) {
  useEffect(() => { trackEvent("view_item", productParameters(product, "product_page")); }, [product]);
  return null;
}
