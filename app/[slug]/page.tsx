import type { Metadata } from "next";
import { notFound, redirect } from "next/navigation";
import { SiteShell } from "@/components/public/site-shell";
import { publicGet, PublicApiError } from "@/lib/public-api";
import type { ApiResponse } from "@/types/api";
import { siteOrigin } from "@/lib/seo";

type CmsPage = { title: string; slug: string; legal_key: string | null; excerpt: string | null; content: string; updated_at: string; seo: { meta_title: string | null; meta_description: string | null } };
async function load(slug: string) { return (await publicGet<ApiResponse<CmsPage>>(`pages/${encodeURIComponent(slug)}`)).data; }
export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
  const { slug } = await params;
  try { const page = await load(slug); return { title: page.seo.meta_title || page.title, description: page.seo.meta_description || page.excerpt || undefined, alternates: { canonical: `${siteOrigin}/${encodeURIComponent(page.slug)}` }, robots: { index: true } }; }
  catch (error) { if (error instanceof PublicApiError && error.status === 404) return { robots: { index: false } }; throw error; }
}
export default async function Page({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  let page: CmsPage;
  try { page = await load(slug); } catch (error) { if (error instanceof PublicApiError && error.status === 404) notFound(); throw error; }
  if (page.slug !== slug) redirect(`/${encodeURIComponent(page.slug)}`);
  return <SiteShell><article className="dewdora-content mx-auto max-w-3xl"><h1 className="mb-6 text-4xl font-black">{page.title}</h1><div className="leading-8 text-[#34554a]" dangerouslySetInnerHTML={{ __html: page.content }} /><p className="mt-8 text-sm text-[#567069]">Updated {new Date(page.updated_at).toLocaleDateString()}</p></article></SiteShell>;
}
