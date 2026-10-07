"use client";

import { useState, type FormEvent } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import PageTitle from "@/components/admin/page-title";
import { getSources, saveSource, sourceAction, approveSourceDocument, importSourceCsv, type Source } from "@/services/agent-setup";
import { apiErrorMessage } from "@/lib/api-error";

const types = ["OFFICIAL_WEBSITE", "OFFICIAL_DOCS", "API", "RSS_OR_FEED", "MANUAL", "CSV_IMPORT", "WEBHOOK"];
const date = (value: string | null) => value ? new Date(value).toLocaleString() : "Never";

export default function Page() {
  const client = useQueryClient();
  const [busy, setBusy] = useState<{ id: number; kind: "test" | "sync" } | null>(null);
  const q = useQuery({ queryKey: ["agent-sources"], queryFn: getSources, refetchInterval: query => busy !== null || query.state.data?.some(s => s.status === "SYNCING") ? 2000 : false });
  const [form, setForm] = useState({ id: 0, brand_id: "", name: "", type: "OFFICIAL_WEBSITE", url: "", notes: "", allowed_domains: "", enabled: true });
  const refresh = () => client.invalidateQueries({ queryKey: ["agent-sources"] });

  async function run(task: () => Promise<unknown>, message: string) {
    try { await task(); toast.success(message); } catch (e) { toast.error(apiErrorMessage(e, "Action failed.")); }
    finally { await refresh(); }
  }
  async function action(source: Source, kind: "test" | "sync") {
    if (busy !== null || source.status === "SYNCING") return;
    setBusy({ id: source.id, kind });
    // Refetch after the request resolves in either direction; Test never changes sync state.
    try {
      const result = await sourceAction(source.id, kind);
      toast[result.ok ? "success" : "error"](result.message || (kind === "test" ? "Source tested." : "Source synchronized."));
    } catch (e) { toast.error(apiErrorMessage(e, `${kind === "test" ? "Test" : "Synchronization"} failed.`)); }
    finally { try { await refresh(); } finally { setBusy(null); } }
  }
  function edit(s: Source) { setForm({ id: s.id, brand_id: String(s.brand_id), name: s.name, type: s.type, url: s.url || "", notes: s.notes || "", allowed_domains: (s.allowed_domains || []).join(", "), enabled: s.enabled }); }
  async function submit(e: FormEvent) {
    e.preventDefault();
    try {
      const saved: Source = await saveSource({ brand_id: Number(form.brand_id), name: form.name, type: form.type, url: form.url || null, notes: form.notes, allowed_domains: form.allowed_domains.split(",").map(x => x.trim()).filter(Boolean), enabled: form.enabled, frequency_hours: 24, priority: 50 }, form.id || undefined);
      setForm(current => ({ ...current, id: saved.id, allowed_domains: (saved.allowed_domains || []).join(", ") }));
      toast.success("Source saved."); await refresh();
    } catch (error) { toast.error(apiErrorMessage(error, "Could not save source.")); }
  }

  return <><PageTitle title="Affiliate sources" description="Test connectivity separately from syncing. Inspect extracted facts before approving them for generation." />
    <form onSubmit={submit} className="mb-7 grid gap-3 rounded-lg border bg-background p-4 sm:grid-cols-2">
      <h2 className="sm:col-span-2 font-semibold">{form.id ? "Edit source" : "Add source"}</h2>
      <label>Brand ID<input className="w-full rounded border p-2" type="number" min="1" required value={form.brand_id} onChange={e => setForm({ ...form, brand_id: e.target.value })} /></label>
      <label>Name<input className="w-full rounded border p-2" required value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} /></label>
      <label>Type<select className="w-full rounded border p-2" value={form.type} onChange={e => setForm({ ...form, type: e.target.value })}>{types.map(x => <option key={x}>{x}</option>)}</select></label>
      <label>Official HTTPS URL<input className="w-full rounded border p-2" type="url" value={form.url} onChange={e => setForm({ ...form, url: e.target.value })} /></label>
      <label>Allowed domains (comma separated)<input className="w-full rounded border p-2" value={form.allowed_domains} onChange={e => setForm({ ...form, allowed_domains: e.target.value })} placeholder="elevenlabs.io" /><span className="text-xs text-muted-foreground">HTTPS URLs are converted to hostnames; IP addresses are ignored.</span></label>
      <label>Notes / manually approved facts<textarea className="w-full rounded border p-2" value={form.notes} onChange={e => setForm({ ...form, notes: e.target.value })} /></label>
      <label className="flex items-center gap-2"><input type="checkbox" checked={form.enabled} onChange={e => setForm({ ...form, enabled: e.target.checked })} />Enabled</label>
      <div><button className="rounded bg-primary px-4 py-2 text-primary-foreground" type="submit">Save source</button></div>
    </form>
    {q.isPending && <p>Loading sources…</p>}{q.isError && <p role="alert">Could not load sources.</p>}
    <div className="space-y-4">{q.data?.map(s => {
      const activeSync = s.status === "SYNCING" && !s.sync_is_stale;
      const syncing = activeSync || busy?.id === s.id;
      const syncActionInFlight = activeSync || (busy?.id === s.id && busy.kind === "sync");
      const latest = s.runs[0];
      return <article key={s.id} className="rounded-lg border bg-background p-4">
        <h2 className="font-semibold">{s.name} · {s.type}</h2>
        <p className="break-all text-sm">{s.url || "Manual notes / import"}</p>
        <dl className="my-3 grid gap-x-4 gap-y-1 text-sm sm:grid-cols-2 lg:grid-cols-3">
          <div><dt className="font-medium">Status</dt><dd>{s.enabled ? (busy?.id === s.id && busy.kind === "sync" ? "SYNCING" : s.status === "SYNCING" && !activeSync ? "SYNCING · interrupted; retry available" : s.status) : "DISABLED"}</dd></div>
          <div><dt className="font-medium">Last tested</dt><dd>{date(s.last_tested_at)}{s.last_test_status ? ` · ${s.last_test_status}` : ""}</dd></div>
          <div><dt className="font-medium">Last sync</dt><dd>{date(s.last_synced_at)}</dd></div>
          <div><dt className="font-medium">Next sync</dt><dd>{s.enabled && s.next_sync_at ? date(s.next_sync_at) : "Not scheduled"}</dd></div>
          <div><dt className="font-medium">Last HTTP status</dt><dd>{s.last_http_status ?? "—"}</dd></div>
          <div><dt className="font-medium">Extracted items (latest run)</dt><dd>{latest?.extracted_items ?? "—"}{latest?.content_changed != null ? ` · ${latest.content_changed ? "changed" : "unchanged"}` : ""}</dd></div>
        </dl>
        {s.last_test_error && <p role="alert" className="text-sm text-destructive">Test error: {s.last_test_error}</p>}
        {s.last_error && <p role="alert" className="text-sm text-destructive">Sync error: {s.last_error}</p>}
        <div className="my-3 flex flex-wrap gap-3 text-sm">
          <button className="underline" onClick={() => edit(s)}>Edit</button>
          {!["CSV_IMPORT", "WEBHOOK"].includes(s.type) && <button className="underline disabled:opacity-50" disabled={syncing} onClick={() => void action(s, "test")}>Test</button>}
          {!["CSV_IMPORT", "WEBHOOK"].includes(s.type) && <button className="underline disabled:opacity-50" disabled={syncing || !s.enabled} onClick={() => void action(s, "sync")}>{syncActionInFlight ? "Syncing…" : "Sync Now"}</button>}
          <button className="underline" onClick={() => void run(() => saveSource({ brand_id: s.brand_id, name: s.name, type: s.type, url: s.url, allowed_domains: s.allowed_domains, notes: s.notes, enabled: !s.enabled }, s.id), "Source updated.")}>{s.enabled ? "Disable" : "Enable"}</button>
        </div>
        {s.type === "CSV_IMPORT" && <label className="text-sm">Import CSV with title,body,source_url columns <input type="file" accept=".csv,text/csv" onChange={e => { const file = e.target.files?.[0]; if (file) void run(() => importSourceCsv(s.id, file), "CSV imported for review."); }} /></label>}
        {s.runs.length > 0 && <details className="mt-2 text-sm"><summary>Synchronization runs ({s.runs.length})</summary>{s.runs.map(run => <p className="mt-1" key={run.id}>{run.status} · {date(run.started_at)} → {date(run.finished_at)} · HTTP {run.http_status ?? "—"} · {run.extracted_items} item(s){run.error ? ` · ${run.error}` : ""}</p>)}</details>}
        {s.documents.map(d => <div key={d.id} className="mt-3 rounded border p-3 text-sm"><p>{d.title} · {d.status} · {d.synced_at}</p><p className="break-all">{d.source_url}</p><details><summary>View imported text</summary><pre className="max-h-60 overflow-auto whitespace-pre-wrap">{d.body}</pre></details>{d.status === "PENDING" && <button className="mt-2 underline" onClick={() => void run(() => approveSourceDocument(d.id), "Source knowledge approved.")}>Approve factual source</button>}</div>)}
      </article>;
    })}</div>
  </>;
}
