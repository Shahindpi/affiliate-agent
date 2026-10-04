"use client";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import PageTitle from "@/components/admin/page-title";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { agentOverview } from "@/services/affiliate-agent";
export default function Page() {
  const q = useQuery({ queryKey: ["agent-overview"], queryFn: agentOverview, refetchInterval: 10000 });
  return <><PageTitle title="Affiliate Agent" description="Create one master video, review every component, revise and approve the exact version." />
    <div className="mb-6 flex gap-4"><Link className="rounded-lg bg-primary px-4 py-2 text-primary-foreground" href="/admin/affiliate-agent/contents/new">Create content</Link><Link className="rounded-lg border bg-background px-4 py-2" href="/admin/affiliate-agent/review">Open review queue</Link></div>
    {q.isPending && <p>Loading agent status…</p>}{q.isError && <p role="alert">Could not load agent status. Check the API connection.</p>}
    {q.data && <><div className="grid gap-4 sm:grid-cols-3">{["REVIEW_PENDING", "REVISION_PENDING", "FINAL_APPROVED"].map(status => <Card key={status}><CardHeader><CardTitle>{status.replaceAll("_", " ")}</CardTitle></CardHeader><CardContent className="text-3xl font-bold">{q.data.counts[status] || 0}</CardContent></Card>)}</div>
      <Card className="mt-6"><CardHeader><CardTitle>Provider status</CardTitle></CardHeader><CardContent className="space-y-2"><p>Revision provider: <strong>{q.data.ai_provider}</strong> · Voice provider: <strong>{q.data.voice_provider}</strong></p><p>{q.data.publishing}</p>{(q.data.ai_provider === "mock" || q.data.voice_provider === "mock") && <p className="rounded-lg bg-amber-100 p-3 text-amber-950">Mock output is labelled. Mock voice uses a test tone. Natural-language feedback requires a configured AI provider.</p>}</CardContent></Card>
      <Card className="mt-6"><CardHeader><CardTitle>Usage this month</CardTitle></CardHeader><CardContent><div className="overflow-x-auto"><table className="w-full text-left"><thead><tr>{["Provider", "Operation", "Jobs", "Tokens in / out", "Characters", "Estimated cost"].map(t => <th className="p-2" key={t}>{t}</th>)}</tr></thead><tbody>{q.data.usage.map(u => <tr className="border-t" key={`${u.provider}-${u.operation}`}><td className="p-2">{u.provider}</td><td className="p-2">{u.operation}</td><td className="p-2">{u.jobs}</td><td className="p-2">{u.input_tokens} / {u.output_tokens}</td><td className="p-2">{u.characters}</td><td className="p-2">{u.estimated_cost === null ? "Not configured" : `$${Number(u.estimated_cost).toFixed(4)}`}</td></tr>)}</tbody></table></div></CardContent></Card></>}
  </>;
}
