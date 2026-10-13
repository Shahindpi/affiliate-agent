"use client";
import { useState, type FormEvent } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import PageTitle from "@/components/admin/page-title";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { agentMedia, agentMediaLimits, uploadAgentMedia } from "@/services/affiliate-agent";
import { validateAgentMediaFile } from "@/lib/affiliate-media-upload";
import { apiErrorMessage } from "@/lib/api-error";
import { fileUploadClass } from "@/lib/file-upload-style";

export default function Page() {
  const [busy, setBusy] = useState(false);
  const [file, setFile] = useState<File | null>(null);
  const client = useQueryClient();
  const q = useQuery({ queryKey: ["agent-media"], queryFn: agentMedia });
  const limits = useQuery({ queryKey: ["agent-media-limits"], queryFn: agentMediaLimits });
  async function upload(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    if (!file || !limits.data) return;
    const error = validateAgentMediaFile(file, limits.data);
    if (error) { toast.error(error); return; }
    const form = e.currentTarget;
    const data = new FormData(form);
    setBusy(true);
    try {
      await uploadAgentMedia(data);
      toast.success("Agent asset uploaded.");
      form.reset(); setFile(null);
      await client.invalidateQueries({ queryKey: ["agent-media"] });
    } catch (err) { toast.error(apiErrorMessage(err)); } finally { setBusy(false); }
  }
  return <>
    <PageTitle title="Agent media assets" description="Upload images and MP4 footage for video scenes. Versioned content keeps immutable copies of selected media." />
    <p className="mb-3 text-sm text-muted-foreground" role="note">JPEG, PNG, WebP and MP4. {limits.data ? `Images: max ${limits.data.image} MB · Videos: max ${limits.data.video} MB.` : "Loading upload limits…"}</p>
    {limits.isError && <p role="alert">Could not load upload limits. Upload is unavailable.</p>}
    <form onSubmit={upload} className="mb-6 flex max-w-3xl flex-wrap items-center gap-3">
      <Input className="max-w-xs" name="name" aria-label="Asset name" placeholder="Asset name" required maxLength={255} />
      <input className={fileUploadClass} name="file" aria-label="Asset file" type="file" accept="image/jpeg,image/png,image/webp,video/mp4" required onChange={e => setFile(e.target.files?.[0] || null)} />
      <Button type="submit" disabled={busy || !limits.data}>Upload asset</Button>
    </form>
    {q.isPending && <p>Loading assets…</p>}{q.isError && <p role="alert">Could not load assets.</p>}
    <div className="overflow-x-auto rounded-lg border bg-background"><table className="w-full text-left"><thead><tr><th className="p-3">ID</th><th className="p-3">Asset</th><th className="p-3">Type</th></tr></thead><tbody>{q.data?.map(m => <tr className="border-t" key={m.id}><td className="p-3">{m.id}</td><td className="p-3">{m.name}</td><td className="p-3">{m.mime_type}</td></tr>)}</tbody></table>{q.data?.length === 0 && <p className="p-5">No agent assets uploaded yet.</p>}</div>
  </>;
}
