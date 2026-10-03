"use client";
import { routes } from "@/lib/routes";
import Link from "next/link";
import type { ReactNode } from "react";
import { trackEvent, productParameters } from "@/lib/analytics";
import type { PublicProduct } from "@/lib/public-api";

export function TrackedProductLink({ product, placement, className, children }: { product: PublicProduct; placement: string; className?: string; children: ReactNode }) {
  return <Link href={routes.products.show(product.slug)} className={className} onClick={() => trackEvent("select_item", productParameters(product, placement))}>{children}</Link>;
}
