"use client";
import { useState, type FormEvent } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import PageTitle from "@/components/admin/page-title";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { agentMedia, uploadAgentMedia } from "@/services/affiliate-agent";
import { apiErrorMessage } from "@/lib/api-error";
export default function Page() {
  const [busy, setBusy] = useState(false); const client = useQueryClient();
  const q = useQuery({ queryKey: ["agent-media"], queryFn: agentMedia });
  async function upload(e: FormEvent<HTMLFormElement>) { e.preventDefault(); const form = e.currentTarget; setBusy(true); try { await uploadAgentMedia(new FormData(form)); toast.success("Agent asset uploaded."); form.reset(); await client.invalidateQueries({ queryKey: ["agent-media"] }); } catch (err) { toast.error(apiErrorMessage(err)); } finally { setBusy(false); } }
  return <><PageTitle title="Agent media assets" description="Upload screenshots and demo recordings for video scenes. Versioned content keeps immutable copies of selected media." /><form onSubmit={upload} className="mb-6 flex max-w-3xl flex-wrap items-center gap-3"><Input className="max-w-xs" name="name" aria-label="Asset name" placeholder="Asset name" required maxLength={255} /><input name="file" aria-label="Asset file" type="file" accept="image/png,image/jpeg,image/webp,video/mp4,audio/mpeg,audio/wav" required /><Button type="submit" disabled={busy}>Upload asset</Button></form><p className="mb-5 text-sm text-muted-foreground">PNG, JPEG, WebP, MP4, MP3 or WAV · maximum 100 MB. The existing Dewdora image library continues to work independently.</p>{q.isPending && <p>Loading assets…</p>}{q.isError && <p role="alert">Could not load assets.</p>}<div className="overflow-x-auto rounded-lg border bg-background"><table className="w-full text-left"><thead><tr><th className="p-3">ID</th><th className="p-3">Asset</th><th className="p-3">Type</th></tr></thead><tbody>{q.data?.map(m => <tr className="border-t" key={m.id}><td className="p-3">{m.id}</td><td className="p-3">{m.name}</td><td className="p-3">{m.mime_type}</td></tr>)}</tbody></table>{q.data?.length === 0 && <p className="p-5">No agent assets uploaded yet.</p>}</div></>;
}
