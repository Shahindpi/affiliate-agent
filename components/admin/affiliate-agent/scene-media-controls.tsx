"use client";

import { useEffect, useState, type FormEvent } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { apiErrorMessage } from "@/lib/api-error";
import { searchAgentMedia, previewAgentMedia, reviseContent, uploadAgentMedia, agentMediaLimits } from "@/services/affiliate-agent";
import { validateAgentMediaFile } from "@/lib/affiliate-media-upload";
import { fileUploadClass } from "@/lib/file-upload-style";
import type { AgentMedia, Scene, Version } from "@/types/affiliate-agent";

type Source = "auto" | "existing" | "upload_image" | "upload_video";
type Draft = { source: Source; media_id: number | null; media_locked: boolean; media_fit: NonNullable<Scene["media_fit"]>; media_focus: NonNullable<Scene["media_focus"]>; overlay_mode: NonNullable<Scene["overlay_mode"]>; media_origin?: Scene["media_origin"]; media_type?: Scene["media_type"] };
type Run = (action: () => Promise<unknown>, message: string) => Promise<void>;
const fieldClass = "mt-1 w-full rounded-lg border bg-background p-2 text-sm";

function initial(scene: Scene): Draft {
  const manual = scene.media_mode === "manual" || (!scene.media_mode && Boolean(scene.media_id));
  return { source: manual ? "existing" : "auto", media_id: scene.media_id, media_locked: Boolean(scene.media_locked), media_fit: scene.media_fit || "cover", media_focus: scene.media_focus || "center", overlay_mode: scene.overlay_mode || "auto", media_origin: scene.media_origin, media_type: scene.media_type };
}

function MediaPreview({ mediaId, type }: { mediaId: number; type?: Scene["media_type"] }) {
  const [url, setUrl] = useState<string | null>(null);
  const [failed, setFailed] = useState(false);
  useEffect(() => {
    let active = true; let objectUrl: string | null = null;
    previewAgentMedia(mediaId).then(blob => { objectUrl = URL.createObjectURL(blob); if (active) setUrl(objectUrl); else URL.revokeObjectURL(objectUrl); }).catch(() => { if (active) { setUrl(null); setFailed(true); } });
    return () => { active = false; if (objectUrl) URL.revokeObjectURL(objectUrl); };
  }, [mediaId]);
  if (failed) return <p role="alert" className="text-xs text-destructive">Media preview unavailable. Check that the file still exists.</p>;
  if (!url) return <p className="text-xs text-muted-foreground">Loading selected media preview…</p>;
  return type === "video" ? <video className="max-h-56 w-full rounded-md bg-black object-contain" src={url} controls preload="metadata" /> : <img className="max-h-56 w-full rounded-md bg-black object-contain" src={url} alt="Selected scene background" />;
}

export default function SceneMediaControls({ version, contentId, disabled, run }: { version: Version; contentId: number; disabled: boolean; run: Run }) {
  const client = useQueryClient();
  const [drafts, setDrafts] = useState<Record<string, Draft>>(() => Object.fromEntries(version.snapshot.scenes.map(s => [s.id, initial(s)])));
  const [dirty, setDirty] = useState<string[]>([]);
  const [search, setSearch] = useState("");
  const [uploaded, setUploaded] = useState<Record<number, AgentMedia>>({});
  const [uploading, setUploading] = useState<string | null>(null);
  const media = useQuery({ queryKey: ["agent-media", search], queryFn: () => searchAgentMedia(search) });
  const limits = useQuery({ queryKey: ["agent-media-limits"], queryFn: agentMediaLimits });
  function change(id: string, values: Partial<Draft>) { setDrafts(old => ({ ...old, [id]: { ...old[id], ...values } })); setDirty(old => old.includes(id) ? old : [...old, id]); }
  async function upload(scene: Scene, file: File) {
    const draft = drafts[scene.id];
    if (!limits.data) { toast.error("Upload limits are unavailable. Try again shortly."); return; }
    const error = validateAgentMediaFile(file, limits.data, draft.source === "upload_video" ? "video" : "image");
    if (error) { toast.error(error); return; }
    setUploading(scene.id);
    try {
      const form = new FormData(); form.set("file", file); form.set("name", file.name.replace(/\.[^.]+$/, "").replaceAll(/[-_]/g, " "));
      const asset = await uploadAgentMedia(form);
      setUploaded(old => ({ ...old, [asset.id]: asset }));
      change(scene.id, { media_id: asset.id, media_origin: "uploaded", media_type: asset.mime_type === "video/mp4" ? "video" : "image" });
      await client.invalidateQueries({ queryKey: ["agent-media"] });
      toast.success("Media uploaded. Save the scene revision to use it in the video.");
    } catch (error) { toast.error(apiErrorMessage(error)); } finally { setUploading(null); }
  }
  async function submit(e: FormEvent) {
    e.preventDefault();
    if (!dirty.length) return;
    const scenes = version.snapshot.scenes.map(scene => {
      if (!dirty.includes(scene.id)) return scene;
      const draft = drafts[scene.id]; const automatic = draft.source === "auto";
      if (!automatic && !draft.media_id) throw new Error(`Select or upload media for scene ${scene.id}.`);
      const next: Scene = { ...scene, media_mode: automatic ? "auto" : "manual", media_id: draft.media_id, media_locked: draft.media_locked, media_fit: draft.media_fit, media_focus: draft.media_focus, overlay_mode: draft.overlay_mode, media_origin: automatic ? (draft.media_id ? "auto" : "fallback") : draft.media_origin || "manual", background_type: draft.media_id ? "media" : "animated_abstract" };
      if (automatic && !draft.media_id) { next.background_type = "media"; delete next.media_type; }
      else next.media_type = draft.media_type;
      return next;
    });
    await run(() => reviseContent(contentId, version.id, `Admin updated background media and display settings for ${dirty.join(", ")}.`, "scenes", { scenes }), "Scene media revision queued for review.");
  }
  return <Card><CardHeader><CardTitle>Scene background media</CardTitle></CardHeader><CardContent><p className="mb-4 text-sm text-muted-foreground">Mix manual footage with automatic media on other scenes. Uploading saves an asset; saving the revision applies it to a new version. Unlock a locked background in one revision before replacing it.</p><p className="mb-4 text-sm text-muted-foreground" role="note">{limits.data ? `Images: max ${limits.data.image} MB · Videos: max ${limits.data.video} MB. JPEG, PNG, WebP and MP4.` : "Loading upload limits…"}</p>
    <form className="space-y-4" onSubmit={e => { void submit(e).catch(error => toast.error(error instanceof Error ? error.message : "Could not save scene media.")); }}>
      <label className="block text-sm">Find existing media<Input className="mt-1" value={search} onChange={e => setSearch(e.target.value)} placeholder="Search by asset name" /></label>
      {version.snapshot.scenes.map((scene, i) => {
        const draft = drafts[scene.id]; const asset = uploaded[draft.media_id || 0] || media.data?.find(m => m.id === draft.media_id);
        const type = draft.media_type || (asset ? asset.mime_type === "video/mp4" ? "video" : "image" : undefined);
        const originallyLocked = Boolean(scene.media_locked);
        return <fieldset className="rounded-lg border p-4" key={scene.id} disabled={disabled || uploading === scene.id}><legend className="px-1 font-medium">{i + 1}. {scene.id} · {scene.purpose || "scene"}</legend>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"><label className="text-sm">Background source<select className={fieldClass} value={draft.source} disabled={originallyLocked} onChange={e => { const source = e.target.value as Source; change(scene.id, { source, media_id: source === "auto" ? null : source === "existing" ? null : null, media_origin: source === "auto" ? "auto" : "manual", media_type: undefined }); }}><option value="auto">Auto (agent selects)</option><option value="existing">Select existing media</option><option value="upload_image">Upload image</option><option value="upload_video">Upload video</option></select></label>
          {draft.source === "existing" && <label className="text-sm">Library asset<select className={fieldClass} value={draft.media_id || ""} disabled={originallyLocked} onChange={e => { const id = Number(e.target.value) || null; const selected = media.data?.find(m => m.id === id); change(scene.id, { media_id: id, media_type: selected ? selected.mime_type === "video/mp4" ? "video" : "image" : undefined, media_origin: "manual" }); }}><option value="">Select an image or MP4</option>{draft.media_id && !media.data?.some(m => m.id === draft.media_id) && <option value={draft.media_id}>{asset?.name || `Current asset #${draft.media_id}`}</option>}{media.data?.filter(m => m.mime_type === "video/mp4" || m.mime_type.startsWith("image/")).map(m => <option key={m.id} value={m.id}>{m.name} · {m.mime_type === "video/mp4" ? "video" : "image"}</option>)}</select></label>}
          {(draft.source === "upload_image" || draft.source === "upload_video") && <label className="text-sm">{uploading === scene.id ? "Uploading…" : `Choose ${draft.source === "upload_video" ? "video" : "image"} to upload`}<Input className={`${fileUploadClass} mt-1`} type="file" accept={draft.source === "upload_video" ? "video/mp4" : "image/jpeg,image/png,image/webp"} disabled={originallyLocked || !limits.data} onChange={e => { const file = e.target.files?.[0]; e.currentTarget.value = ""; if (file) void upload(scene, file); }} /></label>}
          <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={draft.media_locked} onChange={e => change(scene.id, { media_locked: e.target.checked })} />Lock this background</label>
          <label className="text-sm">Fit<select className={fieldClass} value={draft.media_fit} onChange={e => change(scene.id, { media_fit: e.target.value as Draft["media_fit"] })}><option value="cover">Cover</option><option value="contain">Contain</option></select></label>
          <label className="text-sm">Focus<select className={fieldClass} value={draft.media_focus} onChange={e => change(scene.id, { media_focus: e.target.value as Draft["media_focus"] })}>{["center", "top", "bottom", "left", "right"].map(value => <option key={value}>{value}</option>)}</select></label>
          <label className="text-sm">Overlay<select className={fieldClass} value={draft.overlay_mode} onChange={e => change(scene.id, { overlay_mode: e.target.value as Draft["overlay_mode"] })}>{["auto", "light", "medium", "strong", "none"].map(value => <option key={value}>{value}</option>)}</select></label></div>
          {draft.media_id && <div className="mt-3 max-w-sm"><p className="mb-2 text-sm">{asset?.name || `Media #${draft.media_id}`} · {type || "loading type"} · {draft.media_origin || (scene.media_mode === "manual" ? "manual" : "existing")}</p><MediaPreview key={draft.media_id} mediaId={draft.media_id} type={type} /></div>}
        </fieldset>;
      })}
      {media.isError && <p role="alert" className="text-sm text-destructive">Could not load the media library.</p>}
      <Button type="submit" disabled={disabled || !dirty.length || Boolean(uploading)}>Save scene backgrounds as new version</Button>
    </form></CardContent></Card>;
}
