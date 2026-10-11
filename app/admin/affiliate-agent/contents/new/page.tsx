"use client";

import Link from "next/link";
import { useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { toast } from "sonner";
import PageTitle from "@/components/admin/page-title";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { getProductOptions, getAdminProducts } from "@/services/admin-products";
import { getAgentVoices } from "@/services/agent-setup";
import { agentMedia, createAgentContent } from "@/services/affiliate-agent";
import { apiErrorMessage } from "@/lib/api-error";
import { platforms, type Scene, type Snapshot } from "@/types/affiliate-agent";

const selectClass = "mt-2 w-full rounded-lg border bg-background p-2";
const templates = [
  ["none", "No overlay"], ["product_title", "Product title frame"], ["feature_highlight", "Feature highlight"],
  ["bullet_point", "Bullet frame"], ["cta_button", "CTA button"], ["cta_badge", "CTA badge"],
  ["link_in_bio", "Link in bio"], ["check_description", "Check description"], ["try_it_here", "Try it here"],
  ["learn_more", "Learn more"], ["visit_website", "Visit website"], ["use_link_below", "Use the link below"],
  ["see_full_details", "See full details"], ["explore_now", "Explore now"], ["affiliate_disclaimer", "Affiliate disclaimer"],
  ["social_outro", "Social outro"], ["closing_end_card", "Closing end card"], ["link_direction", "Link direction"],
  ["lower_third", "Lower-third"], ["tag_badge", "Tag badge"], ["powered_by_dewdora", "Powered by Dewdora"],
] as const;
const plans = [
  { id: "hook", title: "1. Hook / opening", prompt: "What catches attention in the first seconds?", template: "product_title", animation: "scale_in", layout: "center" },
  { id: "demo", title: "2. Demo / body", prompt: "Describe the product action or benefit shown on screen.", template: "feature_highlight", animation: "slide_in", layout: "left" },
  { id: "closing", title: "3. CTA / closing", prompt: "What should viewers remember or do next?", template: "cta_button", animation: "fade_up", layout: "center" },
] as const;

export default function Page() {
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [brand, setBrand] = useState(""); const [product, setProduct] = useState(""); const [url, setUrl] = useState("");
  const [overrideVoice, setOverrideVoice] = useState(false);
  const options = useQuery({ queryKey: ["agent-product-options"], queryFn: getProductOptions });
  const products = useQuery({ queryKey: ["agent-products"], queryFn: () => getAdminProducts({ per_page: "all" }) });
  const media = useQuery({ queryKey: ["agent-media"], queryFn: agentMedia });
  const voices = useQuery({ queryKey: ["agent-voices"], queryFn: getAgentVoices, enabled: overrideVoice });

  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault(); setBusy(true);
    const f = new FormData(e.currentTarget);
    const text = (key: string) => String(f.get(key) || "").trim();
    const script = text("script"), title = text("title"), cta = text("cta") || "Learn more using the link in the description.", disclosure = text("disclosure"), duration = Number(f.get("duration"));
    const preferredStyle = text("visual_style"), preferredBackground = text("background_type");
    const durations = [Number((duration * .25).toFixed(2)), Number((duration * .45).toFixed(2))];
    durations.push(Number((duration - durations[0] - durations[1]).toFixed(2)));
    const scenes: Scene[] = plans.map((plan, i) => {
      const mediaId = Number(f.get(`${plan.id}_media`)) || null;
      const background = preferredBackground === "auto" ? (mediaId ? "media" : "animated_abstract") : preferredBackground;
      return {
        id: plan.id, purpose: plan.id, text: text(`${plan.id}_description`), headline: text(`${plan.id}_headline`), body: text(`${plan.id}_body`),
        duration: durations[i], media_id: mediaId,
        visual_style: (preferredStyle === "auto" ? (plan.id === "demo" ? "product" : plan.id === "hook" ? "technology" : "cinematic") : preferredStyle) as Scene["visual_style"],
        background_type: background as Scene["background_type"],
        animation: text(`${plan.id}_animation`) as Scene["animation"], layout: text(`${plan.id}_layout`) as Scene["layout"],
        template: text(`${plan.id}_template`),
      };
    });
    const snapshot: Snapshot = {
      script, captions: script, cta, disclosure, voice: { voice_id: overrideVoice ? text("voice_id") || null : null }, scenes,
      metadata: Object.fromEntries(platforms.map(p => [p, { title, caption: script, description: script, hashtags: [], affiliate_url: url, cta, disclosure }])) as unknown as Snapshot["metadata"],
    };
    try {
      const content = await createAgentContent({ title, brand_id: Number(brand) || null, affiliate_product_id: Number(product) || null, snapshot });
      toast.success("Master video queued for review."); router.push(`/admin/affiliate-agent/contents/${content.id}`);
    } catch (err) { toast.error(apiErrorMessage(err)); } finally { setBusy(false); }
  }

  return <><PageTitle title="Create review content" description="Plan a hook, product demonstration and closing scene. The master video enters the review queue before publication." />
    <Card className="max-w-5xl"><CardHeader><CardTitle>Master content draft</CardTitle></CardHeader><CardContent><form onSubmit={submit} className="space-y-6">
      <label className="block">Title<Input className="mt-2" name="title" required maxLength={255} /></label>
      <div className="grid gap-4 sm:grid-cols-2"><label>Existing brand<select className={selectClass} value={brand} onChange={e => { setBrand(e.target.value); setProduct(""); }}><option value="">Select brand</option>{options.data?.brands.map(b => <option key={b.id} value={b.id}>{b.name}</option>)}</select></label><label>Existing affiliate product<select className={selectClass} value={product} onChange={e => { setProduct(e.target.value); const p = products.data?.products.find(p => p.id === Number(e.target.value)); if (p) { setUrl(p.affiliate_url || ""); setBrand(String(p.brand_id || "")); } }}><option value="">Select product</option>{products.data?.products.filter(p => !brand || p.brand_id === Number(brand)).map(p => <option key={p.id} value={p.id}>{p.name}</option>)}</select></label></div>
      {(options.isError || products.isError) && <p role="alert">Catalog options could not be loaded. Check the API connection.</p>}
      <label className="block">Affiliate URL<Input className="mt-2" type="url" value={url} onChange={e => setUrl(e.target.value)} required /></label>
      <label className="block">Spoken script<Textarea className="mt-2 min-h-32" name="script" required maxLength={6000} placeholder="Write narration that explains the real product benefit and action. Scene headlines are separate on-screen copy." /></label>
      <div className="grid gap-4 sm:grid-cols-3"><label>Duration (15–45 seconds)<Input className="mt-2" name="duration" type="number" min={15} max={45} defaultValue={25} required /></label><label>Preferred visual style<select className={selectClass} name="visual_style" defaultValue="auto"><option value="auto">Match scene purpose</option>{["technology", "nature", "cinematic", "product", "abstract", "minimal"].map(x => <option key={x} value={x}>{x}</option>)}</select></label><label>Background preference<select className={selectClass} name="background_type" defaultValue="auto"><option value="auto">Use scene media; animated fallback</option><option value="media">Uploaded media</option><option value="animated_abstract">Animated abstract</option><option value="solid_light">Solid white (intentional)</option><option value="solid_dark">Solid black (intentional)</option></select></label></div>
      <p className="text-sm text-muted-foreground">Realistic nature, lifestyle, technology and product visuals use approved uploaded media. Add screenshots or licensed footage in <Link className="underline" href="/admin/affiliate-agent/media">Affiliate Agent Media</Link>. When a scene has no media, the renderer uses a moving abstract background.</p>
      <div className="space-y-4">{plans.map(plan => <fieldset key={plan.id} className="grid gap-3 rounded-lg border p-4 sm:grid-cols-2"><legend className="px-1 font-semibold">{plan.title}</legend><label className="sm:col-span-2">Scene description<Textarea className="mt-2" name={`${plan.id}_description`} required maxLength={500} placeholder={plan.prompt} /></label><label>Headline (large)<Input className="mt-2" name={`${plan.id}_headline`} required maxLength={140} placeholder="Short, direct headline" /></label><label>Body copy (smaller)<Input className="mt-2" name={`${plan.id}_body`} maxLength={500} placeholder="Optional supporting point" /></label><label>Background media<select className={selectClass} name={`${plan.id}_media`}><option value="">Animated abstract fallback</option>{media.data?.filter(m => m.mime_type.startsWith("image/") || m.mime_type === "video/mp4").map(m => <option key={m.id} value={m.id}>{m.name}</option>)}</select></label><label>Reusable overlay<select className={selectClass} name={`${plan.id}_template`} defaultValue={plan.template}>{templates.map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label><label>Text animation<select className={selectClass} name={`${plan.id}_animation`} defaultValue={plan.animation}>{["fade_up", "slide_in", "scale_in", "masked_reveal", "staggered"].map(x => <option key={x} value={x}>{x.replaceAll("_", " ")}</option>)}</select></label><label>Layout<select className={selectClass} name={`${plan.id}_layout`} defaultValue={plan.layout}>{["left", "center", "split"].map(x => <option key={x}>{x}</option>)}</select></label></fieldset>)}</div>
      <label className="block">CTA (optional wording)<Input className="mt-2" name="cta" defaultValue="Try the tool using the link in the description." maxLength={500} /></label>
      <label className="block">Affiliate disclosure<Textarea className="mt-2" name="disclosure" defaultValue="Affiliate link: I may earn a commission at no extra cost to you." required maxLength={500} /></label>
      <details><summary className="cursor-pointer">Voice override (advanced) — use the configured default otherwise</summary><label className="mt-3 flex items-center gap-2"><input type="checkbox" checked={overrideVoice} onChange={e => setOverrideVoice(e.target.checked)} />Override default voice</label>{overrideVoice && <label className="mt-3 block">ElevenLabs voice<select className={selectClass} name="voice_id"><option value="">Use default voice</option>{voices.data?.map(v => <option key={v.voice_id} value={v.voice_id}>{v.name}</option>)}</select>{voices.isError && <span role="alert">Could not load voices; check ElevenLabs settings.</span>}</label>}</details>
      <p className="text-sm text-muted-foreground">Platform metadata stays editable during review. Every revision creates a new version and requires final approval again.</p>
      <Button type="submit" disabled={busy}>Generate master video for review</Button>
    </form></CardContent></Card>
  </>;
}
