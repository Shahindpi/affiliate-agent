import { routes } from "@/lib/routes";
import type { Metadata } from "next";
import { pageMetadata, JsonLd, breadcrumbs } from "@/lib/seo";
import Link from "next/link";
import { notFound } from "next/navigation";
import { SiteShell } from "@/components/public/site-shell";
import { PostCard, ProductCard } from "@/components/public/cards";
import { PublicApiError, publicGet, type PublicPost, type PublicProduct, type PublicCategory, type ListResult } from "@/lib/public-api";
import type { ApiResponse } from "@/types/api";

type CategoryDetail = {
  category: PublicCategory;
  posts: ListResult<PublicPost>;
  products: ListResult<PublicProduct>;
  brands: { id: number; name: string; slug: string }[];
};

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
 const { slug } = await params;
 try { const response = await publicGet<ApiResponse<{ category: PublicCategory }>>(`categories/${encodeURIComponent(slug)}`); const item = response.data.category; return pageMetadata(`/categories/${encodeURIComponent(slug)}`, item.name, item.description || 'Explore categories on Dewdora.', undefined); } catch { return { robots: { index: false } }; }
}
export default async function Page({ params, searchParams }: { params: Promise<{ slug: string }>; searchParams: Promise<{ product_page?: string; page?: string }> }) {
  const { slug } = await params;
  const { product_page, page } = await searchParams;
  let result: ApiResponse<CategoryDetail>;
  try { result = await publicGet(`categories/${encodeURIComponent(slug)}`, { product_page, page }); } catch (error) { if (error instanceof PublicApiError && error.status === 404) notFound(); throw error; }
  const { category, products, brands, posts } = result.data;
  return <SiteShell><JsonLd data={breadcrumbs([{ name: "Home", path: "/" }, { name: "Categories", path: "/categories" }, { name: category.name, path: `/categories/${encodeURIComponent(slug)}` }])} /><h1 className="text-4xl font-black">{category.name}</h1><p className="mt-4 max-w-2xl text-[#567069]">{category.description}</p>
    <section className="mt-10"><h2 className="text-3xl font-bold">Recommended products</h2><div className="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-4">{products?.data?.map(product => <ProductCard key={product.id} product={product} placement="category_page" />)}</div>{!products?.data?.length && <p className="mt-5 text-[#567069]">No active products in this category yet.</p>}{products?.meta && products.meta.last_page > 1 && <nav aria-label="Product pages" className="mt-8 flex gap-4">{products.meta.current_page > 1 && <Link href={`?product_page=${products.meta.current_page - 1}&page=${page || 1}`} className="rounded-lg border px-4 py-2">Previous</Link>}<span>Page {products.meta.current_page} of {products.meta.last_page}</span>{products.meta.current_page < products.meta.last_page && <Link href={`?product_page=${products.meta.current_page + 1}&page=${page || 1}`} className="rounded-lg border px-4 py-2">Next</Link>}</nav>}</section>
    {brands?.length > 0 && <section className="mt-14"><h2 className="text-2xl font-bold">Brands in this category</h2><div className="mt-5 flex flex-wrap gap-3">{brands.map(brand => <Link key={brand.id} href={routes.brands.show(brand.slug)} className="rounded-full border bg-white px-5 py-3 font-semibold">{brand.name}</Link>)}</div></section>}
    <section className="mt-14"><h2 className="text-3xl font-bold">Guides & articles</h2><div className="mt-6 grid gap-6 md:grid-cols-3">{posts?.data?.map(post => <PostCard key={post.id} post={post} />)}</div>{!posts?.data?.length && <p className="mt-5 text-[#567069]">No published guides in this category yet.</p>}{posts?.meta && posts.meta.last_page > 1 && <nav aria-label="Article pages" className="mt-8 flex gap-4">{posts.meta.current_page > 1 && <Link href={`?page=${posts.meta.current_page - 1}&product_page=${product_page || 1}`} className="rounded-lg border px-4 py-2">Previous</Link>}<span>Page {posts.meta.current_page} of {posts.meta.last_page}</span>{posts.meta.current_page < posts.meta.last_page && <Link href={`?page=${posts.meta.current_page + 1}&product_page=${product_page || 1}`} className="rounded-lg border px-4 py-2">Next</Link>}</nav>}</section>
  </SiteShell>;
}
