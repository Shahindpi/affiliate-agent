"use client";
import { useState } from "react";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import PageTitle from "@/components/admin/page-title";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { agentContents } from "@/services/affiliate-agent";
export default function ContentList({ review = false }: { review?: boolean }) {
  const [status, setStatus] = useState(review ? "REVIEW_PENDING" : ""); const [search, setSearch] = useState(""); const [page, setPage] = useState(1);
  const q = useQuery({ queryKey: ["agent-contents", status, search, page], queryFn: () => agentContents({ ...(status ? { status } : {}), search, page }), refetchInterval: 5000 });
  return <><PageTitle title={review ? "Content review queue" : "Content & version history"} description="Preview, provide feedback, compare versions and grant final approval." /><div className="mb-6 flex flex-wrap items-center gap-3"><Input className="max-w-sm" aria-label="Search content" placeholder="Search content" value={search} onChange={e => { setSearch(e.target.value); setPage(1); }} /><select className="rounded-lg border bg-background p-2" aria-label="Content status" value={status} onChange={e => { setStatus(e.target.value); setPage(1); }}><option value="">All statuses</option>{["GENERATING", "GENERATION_FAILED", "REVIEW_PENDING", "REVISION_PENDING", "FINAL_APPROVED", "REJECTED"].map(s => <option key={s}>{s}</option>)}</select><Link className="rounded-lg bg-primary px-4 py-2 text-sm text-primary-foreground" href="/admin/affiliate-agent/contents/new">Create content</Link></div>
    {q.isPending && <p>Loading content…</p>}{q.isError && <p role="alert">Could not load content. Check the API connection.</p>}
    <div className="overflow-x-auto rounded-xl border bg-background"><table className="w-full text-left text-sm"><thead><tr>{["Content", "Brand / product", "Status", "Review"].map(t => <th key={t} className="p-4">{t}</th>)}</tr></thead><tbody>{q.data?.data.map(c => <tr key={c.id} className="border-t"><td className="p-4 font-medium">{c.title}</td><td className="p-4">{c.brand?.name || "—"}<br />{c.product?.name}</td><td className="p-4">{c.status}</td><td className="p-4"><Link className="text-primary underline" href={`/admin/affiliate-agent/contents/${c.id}`}>Open content</Link></td></tr>)}</tbody></table>{q.data?.data.length === 0 && <p className="p-8 text-center text-muted-foreground">No content found.</p>}</div>
    {q.data && <div className="mt-4 flex items-center gap-4"><Button variant="outline" disabled={page <= 1} onClick={() => setPage(p => p - 1)}>Previous</Button><p>Page {q.data.current_page} of {q.data.last_page} · {q.data.total} items</p><Button variant="outline" disabled={page >= q.data.last_page} onClick={() => setPage(p => p + 1)}>Next</Button></div>}
  </>;
}
