import type { Metadata } from "next";
import { pageMetadata, JsonLd, breadcrumbs } from "@/lib/seo";
import { notFound } from "next/navigation";
import Link from "next/link";
import { SiteShell } from "@/components/public/site-shell";
import { PostCard } from "@/components/public/cards";
import { PublicApiError, publicGet, type PublicPost, type ListResult } from "@/lib/public-api";
import type { ApiResponse } from "@/types/api";
export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
 const { slug } = await params;
 try { const response = await publicGet<ApiResponse<{ tag: { name: string } }>>(`tags/${encodeURIComponent(slug)}`); const item = response.data.tag; return pageMetadata(`/tags/${encodeURIComponent(slug)}`, item.name, 'Explore tags on Dewdora.', undefined); } catch { return { robots: { index: false } }; }
}
export default async function Page({ params, searchParams }: { params: Promise<{ slug: string }>; searchParams: Promise<{ page?: string }> }) {
 const { slug } = await params; let result: ApiResponse<{ tag: { name: string }; posts: ListResult<PublicPost> }>;
 const { page } = await searchParams;
 try { result = await publicGet(`tags/${encodeURIComponent(slug)}`, { page }); } catch (error) { if (error instanceof PublicApiError && error.status === 404) notFound(); throw error; }
 const posts = result.data.posts;
 return <SiteShell><JsonLd data={breadcrumbs([{ name: "Home", path: "/" }, { name: "Tags", path: "/tags" }, { name: result.data.tag.name, path: `/tags/${encodeURIComponent(slug)}` }])} /><h1 className="text-4xl font-black">#{result.data.tag.name}</h1><div className="mt-9 grid gap-6 md:grid-cols-3">{posts.data.map(post => <PostCard key={post.id} post={post} />)}</div>{!posts.data.length && <p className="mt-6 text-[#567069]">No published articles with this tag yet.</p>}{posts.meta && posts.meta.last_page > 1 && <nav aria-label="Article pages" className="mt-8 flex gap-4">{posts.meta.current_page > 1 && <Link href={`?page=${posts.meta.current_page - 1}`} className="rounded-lg border px-4 py-2">Previous</Link>}<span>Page {posts.meta.current_page} of {posts.meta.last_page}</span>{posts.meta.current_page < posts.meta.last_page && <Link href={`?page=${posts.meta.current_page + 1}`} className="rounded-lg border px-4 py-2">Next</Link>}</nav>}</SiteShell>;
}
