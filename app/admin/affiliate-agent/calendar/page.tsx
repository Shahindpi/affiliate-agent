"use client";
import Link from "next/link";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import PageTitle from "@/components/admin/page-title";
import { getAgentCalendar, getSetup } from "@/services/agent-setup";
const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
export default function Page() {
  const [date, setDate] = useState(() => new Date()); const [view, setView] = useState<"day" | "week" | "month">("week");
  const from = new Date(date), to = new Date(date);
  if (view === "week") { from.setDate(date.getDate() - date.getDay()); to.setDate(from.getDate() + 6); }
  if (view === "month") { from.setDate(1); to.setMonth(from.getMonth() + 1, 0); }
  const start = iso(from), end = iso(to);
  const q = useQuery({ queryKey: ["agent-calendar", start, end], queryFn: () => getAgentCalendar(start, end) });
  const setup = useQuery({ queryKey: ["agent-setup"], queryFn: getSetup });
  function move(n: number) { const next = new Date(date); if (view === "month") next.setMonth(next.getMonth() + n); else next.setDate(next.getDate() + n * (view === "week" ? 7 : 1)); setDate(next); }
  const days: Date[] = []; for (const d = new Date(from); d <= to; d.setDate(d.getDate() + 1)) days.push(new Date(d));
  return <><PageTitle title="Affiliate Agent Calendar" description={`Generation, review and platform publishing dates · ${setup.data?.settings.timezone || "local time"}`} />
    <div className="mb-5 flex flex-wrap items-center gap-3"><label>View <select className="rounded border p-2" value={view} onChange={e => setView(e.target.value as typeof view)}><option value="day">Day</option><option value="week">Week</option><option value="month">Month</option></select></label><button className="rounded border px-3 py-2" onClick={() => move(-1)}>Previous</button><button className="rounded border px-3 py-2" onClick={() => setDate(new Date())}>Today</button><button className="rounded border px-3 py-2" onClick={() => move(1)}>Next</button><span>{start} to {end}</span></div>
    {q.isPending && <p>Loading calendar…</p>}{q.isError && <p role="alert">Could not load calendar.</p>}
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{q.data && days.map(day => {
      const key = iso(day);
      const contents = q.data.contents.filter(c => c.planned_for?.slice(0, 10) === key || (!c.planned_for && c.created_at.slice(0, 10) === key));
      const pubs = q.data.publications.filter(p => p.scheduled_at.slice(0, 10) === key);
      return <section className="min-h-36 rounded-lg border bg-background p-3" key={key}><h2 className="font-semibold">{day.toLocaleDateString(undefined, { weekday: "short", month: "short", day: "numeric" })}</h2>
        {contents.map(c => <Link key={c.id} className="mt-2 block rounded bg-muted p-2 text-sm" href={`/admin/affiliate-agent/contents/${c.id}`}>{c.title} · {c.status.replaceAll("_", " ")}</Link>)}
        {pubs.map(p => <Link key={p.id} className="mt-2 block rounded border p-2 text-sm" href={`/admin/affiliate-agent/contents/${p.content_id}`}>{p.platform} · {p.status.replaceAll("_", " ")}{p.content?.title ? ` · ${p.content.title}` : ""}</Link>)}
      </section>;
    })}</div>
  </>;
}
