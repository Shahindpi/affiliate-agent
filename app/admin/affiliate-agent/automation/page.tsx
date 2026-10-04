"use client";
import { useState, type FormEvent } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import PageTitle from "@/components/admin/page-title";
import { getAgentSettings, saveAgentSettings, getAgentVoices, type AgentSetting } from "@/services/agent-setup";
import { apiErrorMessage } from "@/lib/api-error";

export default function Page() {
  const q = useQuery({ queryKey: ["agent-settings"], queryFn: getAgentSettings });
  const voices = useQuery({ queryKey: ["agent-voices"], queryFn: getAgentVoices, retry: false });
  const client = useQueryClient(); const [draft, setDraft] = useState<AgentSetting | null>(null); const form = draft ?? q.data;
  async function submit(e: FormEvent) {
    e.preventDefault(); if (!form) return;
    try { await saveAgentSettings(form); toast.success("Automation saved."); setDraft(null); await client.invalidateQueries({ queryKey: ["agent-settings"] }); }
    catch (err) { toast.error(apiErrorMessage(err, "Could not save settings.")); }
  }
  return <><PageTitle title="Content automation" description="Set generation quotas, rolling content mix and publishing slots. Final approval is always required." />
    {q.isPending && <p>Loading settings…</p>}{q.isError && <p role="alert">Could not load settings.</p>}
    {form && <form className="space-y-5 rounded-lg border bg-background p-5" onSubmit={submit}>
      <div className="grid gap-4 sm:grid-cols-3">
        <label>Timezone<input className="w-full rounded border p-2" value={form.timezone} onChange={e => setDraft({ ...form, timezone: e.target.value })} /></label>
        <label>Videos per day<input className="w-full rounded border p-2" type="number" min="1" max="5" value={form.videos_per_day} onChange={e => setDraft({ ...form, videos_per_day: Number(e.target.value) })} /></label>
        <label>Generation time<input className="w-full rounded border p-2" type="time" value={form.generation_time} onChange={e => setDraft({ ...form, generation_time: e.target.value })} /></label>
        <label>Planning horizon (days)<input className="w-full rounded border p-2" type="number" min="1" max="30" value={form.horizon_days} onChange={e => setDraft({ ...form, horizon_days: Number(e.target.value) })} /></label>
        <label>Master duration (seconds)<input className="w-full rounded border p-2" type="number" min="15" max="45" value={form.duration_seconds} onChange={e => setDraft({ ...form, duration_seconds: Number(e.target.value) })} /></label>
        <label>Publishing slots (comma separated)<input className="w-full rounded border p-2" value={form.publishing_slots.join(", ")} onChange={e => setDraft({ ...form, publishing_slots: e.target.value.split(",").map(x => x.trim()).filter(Boolean) })} /></label>
      </div>
      <div><h2 className="font-semibold">Generation days</h2><div className="flex flex-wrap gap-4">{["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"].map((name, day) => <label key={name}><input type="checkbox" checked={form.generation_days.includes(day)} onChange={e => setDraft({ ...form, generation_days: e.target.checked ? [...form.generation_days, day].sort() : form.generation_days.filter(x => x !== day) })} /> {name}</label>)}</div></div>
      <div className="grid gap-3 sm:grid-cols-2"><label>Enabled brand IDs (blank = all active)<input className="w-full rounded border p-2" value={form.enabled_brand_ids?.join(", ") || ""} onChange={e => setDraft({ ...form, enabled_brand_ids: e.target.value ? e.target.value.split(",").map(x => Number(x.trim())).filter(Boolean) : null })} /></label><label>Enabled campaign IDs (blank = all active)<input className="w-full rounded border p-2" value={form.enabled_campaign_ids?.join(", ") || ""} onChange={e => setDraft({ ...form, enabled_campaign_ids: e.target.value ? e.target.value.split(",").map(x => Number(x.trim())).filter(Boolean) : null })} /></label></div>
      <div><h2 className="font-semibold">Content mix · {Object.values(form.mix).reduce((a, b) => a + b, 0)}%</h2><div className="grid gap-3 sm:grid-cols-5">{Object.entries(form.mix).map(([key, value]) => <label className="capitalize" key={key}>{key.replaceAll("_", " ")}<input className="w-full rounded border p-2" type="number" min="0" max="100" value={value} onChange={e => setDraft({ ...form, mix: { ...form.mix, [key]: Number(e.target.value) } })} /></label>)}</div></div>
      <label>Default ElevenLabs voice<select className="ml-2 rounded border p-2" value={form.default_voice_id || ""} onChange={e => setDraft({ ...form, default_voice_id: e.target.value || null })}><option value="">Use ELEVENLABS_VOICE_ID</option>{voices.data?.map(v => <option key={v.voice_id} value={v.voice_id}>{v.name}</option>)}</select></label>
      {voices.data?.find(v => v.voice_id === form.default_voice_id)?.preview_url && <audio controls src={voices.data.find(v => v.voice_id === form.default_voice_id)?.preview_url || undefined} aria-label="Voice sample" />}
      <div className="flex flex-wrap gap-4"><label><input type="checkbox" checked={form.generation_enabled} onChange={e => setDraft({ ...form, generation_enabled: e.target.checked })} /> Enable daily generation</label><label><input type="checkbox" checked={form.publishing_enabled} onChange={e => setDraft({ ...form, publishing_enabled: e.target.checked })} /> Enable production API publishing</label></div>
      <label>After late approval <select className="rounded border p-2" value={form.late_policy} onChange={e => setDraft({ ...form, late_policy: e.target.value })}><option value="next_slot">Next slot</option><option value="immediate">Immediately</option></select></label>
      <p>Every master video enters Review. Publishing requires exact-version final approval.</p>
      <button type="submit" className="rounded bg-primary px-4 py-2 text-primary-foreground">Save automation</button>
    </form>}
  </>;
}
