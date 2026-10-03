"use client";

import { use } from "react";
import { useQuery } from "@tanstack/react-query";
import { ProductForm } from "@/components/admin/products/product-form";
import { getAdminProduct } from "@/services/admin-products";
import { apiErrorMessage } from "@/lib/api-error";

export default function EditProductPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["admin-product", id],
    queryFn: () => getAdminProduct(Number(id)),
  });
  if (isLoading) return <p>Loading product…</p>;
  if (error) return <p role="alert">{apiErrorMessage(error, "Could not load product.")} <button onClick={() => refetch()} className="underline">Retry</button></p>;
  if (!data) return <p>Product not found.</p>;
  return <div><h1 className="mb-6 text-3xl font-bold">Edit affiliate product</h1><ProductForm product={data} /></div>;
}
