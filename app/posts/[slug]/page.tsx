import { routes } from "@/lib/routes";
import { postMetadata, JsonLd, breadcrumbs, siteOrigin, absoluteImage } from "@/lib/seo";
import Link from "next/link";
import Image from "next/image";
import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { SiteShell } from "@/components/public/site-shell";
import { PostCard, ProductCard } from "@/components/public/cards";
import { PublicApiError, publicGet, type PublicPost, type PublicProduct } from "@/lib/public-api";
import { imageUrl } from "@/lib/image";
import { Comments } from "@/components/public/forms";
import type { ApiResponse } from "@/types/api";

type Detail = ApiResponse<{ post: PublicPost; related_posts: PublicPost[] }>;
export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
 const { slug } = await params;
 try { const result = await publicGet<Detail>(`posts/${encodeURIComponent(slug)}`); return postMetadata(result.data.post); } catch { return { robots: { index: false } }; }
}
export default async function Page({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  let detail: Detail;
  try { detail = await publicGet<Detail>(`posts/${encodeURIComponent(slug)}`); } catch (error) { if (error instanceof PublicApiError && error.status === 404) notFound(); throw error; }
  const { post, related_posts } = detail.data;
  return <SiteShell><JsonLd data={{ "@context": "https://schema.org", "@type": "BlogPosting", headline: post.title, mainEntityOfPage: `${siteOrigin}/posts/${encodeURIComponent(post.slug)}`, ...(post.excerpt ? { description: post.excerpt } : {}), ...(post.featured_image ? { image: absoluteImage(post.featured_image) } : {}), ...(post.published_at ? { datePublished: post.published_at } : {}), ...(post.author?.name ? { author: { "@type": "Person", name: post.author.name } } : {}) }} /><JsonLd data={breadcrumbs([{ name: "Home", path: "/" }, { name: "Articles", path: "/posts" }, { name: post.title, path: `/posts/${encodeURIComponent(post.slug)}` }])} /><article className="mx-auto max-w-3xl"><Link href={routes.posts.index} className="text-sm font-semibold text-[#165e46]">← All articles</Link><p className="mt-10 text-sm font-bold uppercase tracking-widest text-[#2c9873]">{post.category?.name || "Article"}</p><h1 className="mt-3 text-4xl font-black md:text-5xl">{post.title}</h1><p className="mt-4 text-sm text-[#71857f]">{post.author?.name ? `By ${post.author.name}` : "Dewdora editorial"}{post.published_at ? ` • ${new Date(post.published_at).toLocaleDateString()}` : ""}{post.reading_time ? ` • ${post.reading_time} min read` : ""}</p><p className="mt-5 text-lg text-[#567069]">{post.excerpt}</p>{post.featured_image && <Image unoptimized width={1200} height={700} src={imageUrl(post.featured_image)} alt={`${post.title} featured image`} className="mt-8 h-auto w-full rounded-2xl object-cover" />}
    {!!post.affiliate_products?.length && <p className="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>Affiliate disclosure:</strong> This article contains eligible affiliate links. Dewdora may earn a commission at no extra cost to you.</p>}
    {post.content && <div className="dewdora-content mt-9 leading-8 text-[#34554a]" dangerouslySetInnerHTML={{ __html: post.content }} />}
    <div className="mt-8 flex flex-wrap gap-2">{post.tags?.map(tag => <Link key={tag.id} href={routes.tags.show(tag.slug)} className="rounded-full border px-4 py-2 text-sm">#{tag.name}</Link>)}</div>
    {post.allow_comments && <Comments slug={slug} />}</article>
    {!!post.affiliate_products?.length && <section className="mt-16"><h2 className="text-2xl font-bold">Tools mentioned</h2><div className="mt-6 grid gap-6 md:grid-cols-3">{post.affiliate_products.map((product: PublicProduct) => <ProductCard key={product.id} product={product} placement="article_page" />)}</div></section>}
    {!!related_posts?.length && <section className="mt-16"><h2 className="text-2xl font-bold">Keep reading</h2><div className="mt-6 grid gap-6 md:grid-cols-3">{related_posts.map(related => <PostCard key={related.id} post={related} />)}</div></section>}
  </SiteShell>;
}
