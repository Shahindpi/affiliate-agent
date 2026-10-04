"use client";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import PageTitle from "@/components/admin/page-title";
import { getPublishingDashboard } from "@/services/agent-setup";
const platforms = ["pinterest", "instagram", "tiktok", "facebook", "youtube"];
export default function Page() {
  const q = useQuery({ queryKey: ["agent-publishing"], queryFn: getPublishingDashboard, refetchInterval: 10000 });
  return <><PageTitle title="Publishing dashboard" description="Five independent destinations per content item. A successful platform is not sent again after another platform fails." />
    {q.isPending && <p>Loading publishing status…</p>}{q.isError && <p role="alert">Could not load publishing status.</p>}
    <div className="space-y-5">{q.data?.map(c => {
      const published = c.publications.filter(p => ["PUBLISHED", "PUBLISHED_MANUALLY"].includes(p.status)).length;
      const overall = published === 5 ? "PUBLISHED" : published > 0 ? "PARTIALLY_PUBLISHED" : c.status;
      return <section className="rounded-lg border bg-background p-4" key={c.id}><div className="mb-3 flex flex-wrap justify-between gap-3"><Link className="font-semibold underline" href={`/admin/affiliate-agent/contents/${c.id}`}>{c.title}</Link><span>{overall.replaceAll("_", " ")}</span></div><div className="grid gap-2 md:grid-cols-5">{platforms.map(platform => { const p = c.publications.find(x => x.platform === platform && x.version_id === c.current_version_id); return <div className="rounded border p-3 text-sm" key={platform}><strong className="capitalize">{platform}</strong><p>{p?.status.replaceAll("_", " ") || "NOT_SCHEDULED"}</p>{p?.scheduled_at && <p>{new Date(p.scheduled_at).toLocaleString()}</p>}{p?.account && <p>{p.account.name}</p>}{p?.platform_url && <a className="break-all underline" href={p.platform_url} target="_blank" rel="noreferrer">View post</a>}{p?.error && <p role="alert" className="text-destructive">{p.error}</p>}</div>; })}</div></section>;
    })}{q.data?.length === 0 && <p>No content yet.</p>}</div>
  </>;
}
