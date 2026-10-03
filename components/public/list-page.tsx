import Link from "next/link";
import { routes } from "@/lib/routes";
import { SiteShell } from "@/components/public/site-shell";
import { PostCard, ProductCard } from "@/components/public/cards";
import { safePublicGet, type PublicPost, type PublicProduct, type PublicCategory, type ListResult } from "@/lib/public-api";

type Kind = "posts" | "products" | "categories" | "tags" | "brands";
export async function ListPage({ kind, title, query }: { kind: Kind; title: string; query?: Record<string, string | number | undefined> }) {
  const result = await safePublicGet<ListResult<PublicPost | PublicProduct | PublicCategory>>(kind, { data: [] }, query);
  const data = result.data || [];
  const page = Number(query?.page || 1);
  return <SiteShell><h1 className="text-4xl font-black">{title}</h1><p className="mt-3 text-[#567069]">Explore Dewdora&apos;s {title.toLowerCase()}.</p>
    {kind === "posts" || kind === "products" ? <div className="mt-9 grid gap-6 md:grid-cols-3">{data.map(item => kind === "posts" ? <PostCard key={item.id} post={item as PublicPost} /> : <ProductCard key={item.id} product={item as PublicProduct} />)}</div> : <div className="mt-9 grid gap-4 md:grid-cols-3">{data.map(item => <Link key={item.id} href={kind === "categories" ? routes.categories.show(item.slug) : kind === "brands" ? routes.brands.show(item.slug) : routes.tags.show(item.slug)} className="rounded-2xl border border-[#dce6d9] bg-white p-6 hover:shadow-lg"><h2 className="text-xl font-bold">{"name" in item ? item.name : item.title}</h2>{"description" in item && <p className="mt-3 line-clamp-3 text-sm text-[#567069]">{item.description}</p>}</Link>)}</div>}
    {data.length === 0 && <p className="mt-9 text-[#567069]">Nothing published here yet.</p>}
    {result.meta && result.meta.last_page > 1 && <nav aria-label="Pagination" className="mt-10 flex gap-4">{page > 1 && <Link href={`?page=${page - 1}`} className="rounded-lg border px-4 py-2">Previous</Link>}<span className="py-2">Page {page} of {result.meta.last_page}</span>{page < result.meta.last_page && <Link href={`?page=${page + 1}`} className="rounded-lg border px-4 py-2">Next</Link>}</nav>}
  </SiteShell>;
}
