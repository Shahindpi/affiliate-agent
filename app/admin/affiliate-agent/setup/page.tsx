"use client";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import PageTitle from "@/components/admin/page-title";
import { getSetup, testAgentProvider } from "@/services/agent-setup";
import { toast } from "sonner";

const platforms = ["pinterest", "instagram", "tiktok", "facebook", "youtube"];
export default function Page() {
  const q = useQuery({ queryKey: ["agent-setup"], queryFn: getSetup, refetchInterval: 30000 });
  const d = q.data;
  async function test(provider: string) {
    try { const r = await testAgentProvider(provider); toast[r.ok ? "success" : "error"](r.message); await q.refetch(); }
    catch { toast.error("Test could not reach the provider. Check network and server logs."); }
  }
  const providerReady = d ? [d.providers.openai.status, d.providers.elevenlabs.status].filter(x => x === "READY").length : 0;
  const connected = platforms.filter(p => d?.accounts.some(a => a.platform === p && a.status === "CONNECTED")).length;
  const score = providerReady + Math.min(1, d?.sources || 0) + connected + (d?.settings.generation_enabled ? 1 : 0) + (d?.infrastructure.ffmpeg === "READY" ? 1 : 0);
  return <><PageTitle title="Affiliate Agent Setup" description="Check real provider, source, social and worker readiness before enabling automation." />
    {q.isPending && <p>Loading setup…</p>}{q.isError && <p role="alert">Could not load setup.</p>}
    {d && <><p className="mb-5 rounded-lg border bg-background p-4 font-medium">Readiness: {score}/10 checks. API credentials and developer app review are needed for live publishing.</p>
      <div className="grid gap-4 md:grid-cols-2">{["openai", "elevenlabs"].map(p => <section className="rounded-lg border bg-background p-4" key={p}><h2 className="font-semibold capitalize">{p === "openai" ? "OpenAI" : "ElevenLabs Voice"}</h2><p>Status: {d.providers[p].status}</p><div className="mt-2 flex gap-4 text-sm"><Link className="underline" href="/admin/affiliate-agent/settings">Configure</Link><button className="underline" onClick={() => void test(p)}>Test Connection</button></div></section>)}
        <section className="rounded-lg border bg-background p-4"><h2 className="font-semibold">Affiliate content sources</h2><p>{d.sources} approved documents</p>{d.brands.map(s => <p className="mt-1 text-sm" key={s.id}>{s.brand?.name} · {s.name}: {s.status}; last sync {s.last_synced_at ? new Date(s.last_synced_at).toLocaleString() : "never"}{s.last_error ? ` · ${s.last_error}` : ""}</p>)}<Link className="mt-2 inline-block underline" href="/admin/affiliate-agent/sources">Configure / sync sources</Link></section>
        <section className="rounded-lg border bg-background p-4"><h2 className="font-semibold">Infrastructure</h2><p>FFmpeg: {d.infrastructure.ffmpeg} · storage: {d.infrastructure.storage}</p><p>Queue: {d.infrastructure.queue} ({d.infrastructure.queue_driver})</p><p>Scheduler: {d.infrastructure.scheduler}{d.infrastructure.last_tick ? ` · last tick ${new Date(d.infrastructure.last_tick).toLocaleString()}` : " · no recorded tick"}</p><p>Redis: {d.infrastructure.redis.status}</p><div className="mt-2 flex flex-wrap gap-3">{["ffmpeg", "queue", "scheduler"].map(p => <button className="underline" key={p} onClick={() => void test(p)}>Test {p}</button>)}</div></section>
      </div><h2 className="mt-7 text-lg font-semibold">Social accounts</h2><div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">{platforms.map(p => { const accounts = d.accounts.filter(a => a.platform === p); const state = accounts.length ? accounts.map(a => a.status === "CONNECTED" && a.api_review_status === "API_REVIEW_REQUIRED" ? "API_REVIEW_REQUIRED" : a.status).join(", ") : d.social_configured[p === "instagram" || p === "facebook" ? "meta" : p] ? "AUTHORIZATION_REQUIRED" : "NOT_CONFIGURED"; return <Link key={p} className="rounded-lg border bg-background p-3 capitalize" href="/admin/affiliate-agent/social-accounts"><strong>{p}</strong><p className="break-words text-xs">{state}</p></Link>; })}</div>
      <section className="mt-6 rounded-lg border bg-background p-4"><h2 className="font-semibold">Automation</h2><p>{d.settings.generation_enabled ? "Enabled" : "Disabled"} · {d.settings.timezone} · {d.settings.videos_per_day} videos/day · approval required</p><p>Next generation: {d.next_generation_run ? new Date(d.next_generation_run).toLocaleString() : "Not scheduled"}</p><p>Publishing slots: {d.settings.publishing_slots.join(", ")}</p><Link className="mt-2 inline-block underline" href="/admin/affiliate-agent/automation">Configure automation</Link></section>
    </>}
  </>;
}
