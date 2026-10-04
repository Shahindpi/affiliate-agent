"use client";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import PageTitle from "@/components/admin/page-title";
import { listAdminPages } from "@/services/admin-pages";
import { routes } from "@/lib/routes";
export default function Page() {
  const q = useQuery({ queryKey: ["admin-pages"], queryFn: listAdminPages });
  return <><PageTitle title="Pages" description="Edit the Privacy Policy, Terms, Affiliate Disclosure and other public pages in Dewdora." />
    <Link className="mb-5 inline-block rounded bg-primary px-4 py-2 text-primary-foreground" href={routes.admin.pages.create}>Create page</Link>
    {q.isPending && <p>Loading pages…</p>}{q.isError && <p role="alert">Could not load pages.</p>}
    <div className="space-y-3">{q.data?.data.map(page => <article className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-background p-4" key={page.id}><div><strong>{page.title}</strong><p className="text-sm">/{page.slug} · {page.status ? "Published" : "Unpublished"}{page.legal_key ? ` · ${page.legal_key}` : ""}</p></div><div className="flex gap-4"><Link className="underline" href={routes.admin.pages.edit(page.id)}>Edit</Link>{page.status && <Link className="underline" href={`/${page.slug}`}>View</Link>}</div></article>)}</div>
  </>;
}
