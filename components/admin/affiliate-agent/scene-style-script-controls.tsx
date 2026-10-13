"use client";

import { useState, type FormEvent } from "react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { formatScriptSummary, parseScriptSummary } from "@/lib/affiliate-script-summary";
import { reviseContent } from "@/services/affiliate-agent";
import type { Scene, Version } from "@/types/affiliate-agent";

type Run = (action: () => Promise<unknown>, message: string) => Promise<void>;
type SizeField = "headline_font_size" | "body_font_size";
type ColorField = "headline_color" | "body_color" | "cta_text_color" | "cta_background_color" | "cta_border_color";
const colors: { field: ColorField; label: string; fallback: string }[] = [
  { field: "headline_color", label: "Headline text", fallback: "#ffffff" },
  { field: "body_color", label: "Body text", fallback: "#ffffff" },
  { field: "cta_text_color", label: "CTA text", fallback: "#ffffff" },
  { field: "cta_background_color", label: "CTA background", fallback: "#101820" },
  { field: "cta_border_color", label: "CTA border / accent", fallback: "#16805c" },
];

export default function SceneStyleScriptControls({ version, contentId, disabled, run }: { version: Version; contentId: number; disabled: boolean; run: Run }) {
  const [scenes, setScenes] = useState<Scene[]>(() => version.snapshot.scenes.map(scene => ({ ...scene })));
  const [summary, setSummary] = useState(() => formatScriptSummary(version.snapshot.scenes));
  function size(id: string, field: SizeField, value: string) {
    setScenes(current => current.map(scene => { if (scene.id !== id) return scene; const next = { ...scene }; if (value) next[field] = Number(value); else delete next[field]; return next; }));
  }
  function color(id: string, field: ColorField, value: string | null) {
    setScenes(current => current.map(scene => { if (scene.id !== id) return scene; const next = { ...scene }; if (value) next[field] = value; else delete next[field]; return next; }));
  }
  async function submit(event: FormEvent) {
    event.preventDefault();
    const spoken = parseScriptSummary(summary, scenes);
    if (!spoken) { toast.error("Keep each script section heading and provide narration under it (max 500 characters per scene)."); return; }
    const updated = scenes.map((scene, index) => ({ ...scene, text: spoken[index] }));
    if (JSON.stringify(updated) === JSON.stringify(version.snapshot.scenes)) { toast.error("Change the script or scene style before saving."); return; }
    await run(() => reviseContent(contentId, version.id, "Admin updated video script summary and scene typography/CTA styling.", "scenes", { scenes: updated }), "Script and style revision queued for review.");
  }
  return <Card><CardHeader><CardTitle>Video Script Summary & scene styles</CardTitle></CardHeader><CardContent><form className="space-y-5" onSubmit={event => { void submit(event); }}>
    <label className="block text-sm">Video Script Summary<Textarea className="mt-2 min-h-64 font-mono" value={summary} onChange={event => { setSummary(event.target.value); const spoken = parseScriptSummary(event.target.value, scenes); if (spoken) setScenes(current => current.map((scene, index) => ({ ...scene, text: spoken[index] }))); }} /><span className="mt-1 block text-xs text-muted-foreground">Edit narration beneath each heading. This updates scene text, captions, and the generated voice in the new version.</span></label>
    {scenes.map(scene => <fieldset key={scene.id} className="grid gap-3 rounded-lg border p-3 sm:grid-cols-2"><legend className="px-1 font-medium">{scene.id}</legend>
      <label className="text-sm">Headline size (68–104 px)<Input className="mt-1" type="number" min={68} max={104} placeholder="Auto · 80–90" value={scene.headline_font_size ?? ""} onChange={event => size(scene.id, "headline_font_size", event.target.value)} /></label>
      <label className="text-sm">Body size (36–60 px)<Input className="mt-1" type="number" min={36} max={60} placeholder="Auto · 40–50" value={scene.body_font_size ?? ""} onChange={event => size(scene.id, "body_font_size", event.target.value)} /></label>
      {colors.map(item => <label key={item.field} className="text-sm">{item.label}<span className="mt-1 flex items-center gap-2"><input type="checkbox" aria-label={`Custom ${item.label} for ${scene.id}`} checked={Boolean(scene[item.field])} onChange={event => color(scene.id, item.field, event.target.checked ? item.fallback : null)} />Custom</span><Input className="mt-1 h-11" type="color" aria-label={`${item.label} color for ${scene.id}`} value={scene[item.field] || item.fallback} disabled={!scene[item.field]} onChange={event => color(scene.id, item.field, event.target.value)} /></label>)}
      <p className="sm:col-span-2 text-xs text-muted-foreground">Automatic colors adapt to the background. The default CTA has a lightly transparent panel and the existing Dewdora primary accent.</p>
    </fieldset>)}
    <Button type="submit" disabled={disabled}>Save script and styles as a new version</Button>
  </form></CardContent></Card>;
}
