"use client";
import { routes } from "@/lib/routes";

import Link from "next/link";
import Image from "next/image";
import { useState } from "react";
import { PageSizeSelect, type PageSize } from "@/components/admin/page-size";
import { useQuery } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiErrorMessage } from "@/lib/api-error";
import {
  deleteAdminProduct,
  getAdminProducts,
} from "@/services/admin-products";
import type { AffiliateProduct } from "@/types/product";

export default function ProductsPage() {
  const [page, setPage] = useState(1);
  const [size, setSize] = useState<PageSize>(20);
  const [search, setSearch] = useState("");
  const [revision, setRevision] = useState(0);
  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["admin-products", page, search, size, revision],
    queryFn: () => getAdminProducts({ page, search: search || undefined, per_page: size }),
  });
  const products = data?.products || [];
  const meta = data?.meta;
  async function remove(product: AffiliateProduct) {
    if (!window.confirm(`Delete ${product.name}?`)) return;
    try {
      await deleteAdminProduct(product.id);
      toast.success("Product deleted.");
      if (products.length === 1 && page > 1) setPage(page - 1);
      setRevision((value) => value + 1);
    } catch (error) {
      toast.error(apiErrorMessage(error, "Could not delete product."));
    }
  }
  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold">Affiliate products</h1>
          <p className="mt-1 text-muted-foreground">
            Manage offers, affiliate destinations and editorial product data.
          </p>
        </div>
        <Link
          href={routes.admin.products.create}
          className="rounded-lg bg-primary px-5 py-3 text-primary-foreground"
        >
          Add affiliate product
        </Link>
      </div>
      <input
        type="search"
        value={search}
        onChange={(event) => {
          setSearch(event.target.value);
          setPage(1);
        }}
        placeholder="Search products"
        className="mt-6 w-full max-w-sm rounded-lg border p-3"
      />
      <div className="mt-4"><PageSizeSelect value={size} onChange={value => { setSize(value); setPage(1); }} /></div>
      <div className="mt-6 overflow-x-auto rounded-2xl border bg-background">
        <table className="w-full min-w-[900px] text-left text-sm">
          <thead className="bg-muted/50">
            <tr>
              <th className="p-4">Product</th>
              <th className="p-4">Brand</th>
              <th className="p-4">Network</th>
              <th className="p-4">Price</th>
              <th className="p-4">Status</th>
              <th className="p-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            {products.map((product) => (
              <tr key={product.id} className="border-t">
                <td className="p-4">
                  <div className="flex items-center gap-3">
                    {product.featured_image ? (
                      <Image
                        unoptimized
                        width={64}
                        height={48}
                        src={product.featured_image}
                        alt={`${product.name} thumbnail`}
                        className="h-12 w-16 rounded-lg border object-contain"
                      />
                    ) : (
                      <div className="h-12 w-16 rounded-lg bg-muted" />
                    )}
                    <div>
                      <strong>{product.name}</strong>
                      <span className="block text-xs text-muted-foreground">
                        {product.category?.name || "Uncategorized"}
                      </span>
                    </div>
                  </div>
                </td>
                <td className="p-4">{product.brand?.name || "—"}</td>
                <td className="p-4">
                  {product.affiliate_network?.name || "—"}
                </td>
                <td className="p-4">
                  {product.price != null
                    ? `${product.currency || "USD"} ${product.price}`
                    : "—"}
                </td>
                <td className="p-4">
                  {product.status ? "Active" : "Inactive"}
                </td>
                <td className="p-4">
                  <Link
                    href={routes.admin.products.edit(product.id)}
                    className="mr-4 text-primary"
                  >
                    Edit
                  </Link>
                  <a
                    href={product.affiliate_url}
                    target="_blank"
                    rel="noopener noreferrer sponsored"
                    className="mr-4"
                  >
                    Visit
                  </a>
                  <button
                    onClick={() => remove(product)}
                    className="text-destructive"
                  >
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {isLoading && <p className="p-6">Loading products…</p>}
        {error && <p role="alert" className="p-6 text-destructive">{apiErrorMessage(error, "Could not load products.")} <button onClick={() => refetch()} className="underline">Retry</button></p>}
        {!isLoading && !error && !products.length && (
          <p className="p-6 text-muted-foreground">No products found.</p>
        )}
      </div>
      {meta && meta.last_page > 1 && (
        <div className="mt-5 flex gap-3">
          <button
            disabled={page <= 1}
            onClick={() => setPage(page - 1)}
            className="rounded border px-3 py-2"
          >
            Previous
          </button>
          <span className="py-2">
            {page} / {meta.last_page}
          </span>
          <button
            disabled={page >= meta.last_page}
            onClick={() => setPage(page + 1)}
            className="rounded border px-3 py-2"
          >
            Next
          </button>
        </div>
      )}
    </div>
  );
}
