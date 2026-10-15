"use client";



import Link from "next/link";

import { useState, type FormEvent } from "react";

import { useQueryClient } from "@tanstack/react-query";

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

import { agentMedia, agentMediaLimits, createAgentContent, uploadAgentMedia } from "@/services/affiliate-agent";

import { apiErrorMessage } from "@/lib/api-error";

import { platforms, type AgentMedia, type Scene, type Snapshot } from "@/types/affiliate-agent";

import { formatScriptSummary, parseScriptSummary } from "@/lib/affiliate-script-summary";

import { validateAgentMediaFile } from "@/lib/affiliate-media-upload";

import { fileUploadClass } from "@/lib/file-upload-style";



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

  const queryClient = useQueryClient();

  const [busy, setBusy] = useState(false);

  const [narration, setNarration] = useState(["", "", ""]);

  const [duration, setDuration] = useState(25);

  const [ctaPreview, setCtaPreview] = useState("Try the tool using the link in the description.");

  const [sceneCopy, setSceneCopy] = useState<Record<string, { headline: string; body: string }>>({});

  const [summary, setSummary] = useState(formatScriptSummary(plans.map(plan => ({ purpose: plan.id, text: "" }))));

  const [selectedMedia, setSelectedMedia] = useState<Record<string, AgentMedia>>({});

  const [uploadedMedia, setUploadedMedia] = useState<AgentMedia[]>([]);

  const [colorOverrides, setColorOverrides] = useState<Record<string, boolean>>({});

  const [uploadingScene, setUploadingScene] = useState<string | null>(null);

  const [brand, setBrand] = useState(""); const [product, setProduct] = useState(""); const [url, setUrl] = useState("");

  const [overrideVoice, setOverrideVoice] = useState(false);

  const options = useQuery({ queryKey: ["agent-product-options"], queryFn: getProductOptions });

  const products = useQuery({ queryKey: ["agent-products"], queryFn: () => getAdminProducts({ per_page: "all" }) });

  const media = useQuery({ queryKey: ["agent-media"], queryFn: agentMedia });

  const limits = useQuery({ queryKey: ["agent-media-limits"], queryFn: agentMediaLimits });

  const voices = useQuery({ queryKey: ["agent-voices"], queryFn: getAgentVoices, enabled: overrideVoice });



  function updateNarration(index: number, value: string) {

    const next = [...narration]; next[index] = value; setNarration(next);

    setSummary(formatScriptSummary(plans.map((plan, i) => ({ purpose: plan.id, text: next[i] }))));

  }



  async function uploadSceneMedia(id: string, file: File) {

    if (!limits.data) { toast.error("Upload limits are unavailable."); return; }

    const error = validateAgentMediaFile(file, limits.data);

    if (error) { toast.error(error); return; }

    setUploadingScene(id);

    try {

      const form = new FormData(); form.set("file", file); form.set("name", file.name.replace(/\.[^.]+$/, ""));

      const asset = await uploadAgentMedia(form);

      setSelectedMedia(previous => ({ ...previous, [id]: asset }));

      setUploadedMedia(previous => [...previous, asset]);

      await queryClient.invalidateQueries({ queryKey: ["agent-media"] });

      toast.success("Scene media uploaded and selected.");

    } catch (error) { toast.error(apiErrorMessage(error)); } finally { setUploadingScene(null); }

  }



  async function submit(e: FormEvent<HTMLFormElement>) {

    e.preventDefault();

    const spoken = parseScriptSummary(summary, plans);

    if (!spoken) { toast.error("Keep the HOOK, CONTENT 1 and FINISHING / CTA headings, with narration under each (max 500 characters)."); return; }

    const words = spoken.join(" ").trim().split(/\s+/).filter(Boolean).length;

    if (words / 2.3 > duration * 1.12) { toast.error(`Narration is likely too long: ${words} words for ${duration} seconds. Shorten the script before generation.`); return; }

    if (plans.some(plan => (sceneCopy[plan.id]?.headline || "").length > 70 || (sceneCopy[plan.id]?.body || "").length > 115) || ctaPreview.length > 70) { toast.error("Overlay text is too long for a readable vertical video. Shorten the highlighted headline, body or CTA."); return; }

    setBusy(true);

    const f = new FormData(e.currentTarget);

    const text = (key: string) => String(f.get(key) || "").trim();

    const title = text("title"), cta = text("cta") || "Learn more using the link in the description.", disclosure = text("disclosure"), videoDuration = Number(f.get("duration"));

    const preferredStyle = text("visual_style"), preferredBackground = text("background_type");

    const durations = [Number((videoDuration * .25).toFixed(2)), Number((videoDuration * .45).toFixed(2))];

    durations.push(Number((videoDuration - durations[0] - durations[1]).toFixed(2)));

    const scenes: Scene[] = plans.map((plan, i) => {

      const selected = selectedMedia[plan.id];

      const mediaId = selected?.id || null;

      const background = mediaId ? "media" : preferredBackground === "auto" ? "media" : preferredBackground;

      return {

        id: plan.id, purpose: plan.id, text: spoken[i], headline: text(`${plan.id}_headline`), body: text(`${plan.id}_body`),

        duration: durations[i], media_id: mediaId, media_mode: mediaId ? "manual" : "auto", media_origin: mediaId ? "manual" : "auto", media_type: mediaId ? selected.mime_type === "video/mp4" ? "video" : "image" : undefined, media_fit: "cover", media_focus: "center", overlay_mode: "auto",

        ...(f.get(`${plan.id}_headline_size`) ? { headline_font_size: Number(f.get(`${plan.id}_headline_size`)) } : {}),

        ...(f.get(`${plan.id}_body_size`) ? { body_font_size: Number(f.get(`${plan.id}_body_size`)) } : {}),

        text_font_weight: text(`${plan.id}_font_weight`) as Scene["text_font_weight"], text_stroke: text(`${plan.id}_stroke`) as Scene["text_stroke"], text_shadow: text(`${plan.id}_shadow`) as Scene["text_shadow"], text_stroke_color: text(`${plan.id}_stroke_color`),

        ...(colorOverrides[plan.id] ? { headline_color: text(`${plan.id}_headline_color`), body_color: text(`${plan.id}_body_color`), cta_text_color: text(`${plan.id}_cta_text_color`), cta_background_color: text(`${plan.id}_cta_background_color`), cta_border_color: text(`${plan.id}_cta_border_color`) } : {}),

        visual_style: (preferredStyle === "auto" ? (plan.id === "demo" ? "product" : plan.id === "hook" ? "technology" : "cinematic") : preferredStyle) as Scene["visual_style"],

        background_type: background as Scene["background_type"],

        animation: text(`${plan.id}_animation`) as Scene["animation"], layout: text(`${plan.id}_layout`) as Scene["layout"],

        template: text(`${plan.id}_template`),

      };

    });

    const script = scenes.map(scene => scene.text).join(" ");

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

      <p className="text-sm text-muted-foreground">Scene descriptions are spoken in order. Together they form the script and captions. Headlines and body copy are visual summaries.</p>

      <div className="grid gap-4 sm:grid-cols-3"><label>Duration (15–45 seconds)<Input className="mt-2" name="duration" type="number" min={15} max={45} defaultValue={25} onChange={e => setDuration(Number(e.target.value))} required /></label><label>Preferred visual style<select className={selectClass} name="visual_style" defaultValue="auto"><option value="auto">Match scene purpose</option>{["technology", "nature", "cinematic", "product", "abstract", "minimal"].map(x => <option key={x} value={x}>{x}</option>)}</select></label><label>Background preference<select className={selectClass} name="background_type" defaultValue="auto"><option value="auto">Use scene media; animated fallback</option><option value="media">Uploaded media</option><option value="animated_abstract">Animated abstract</option><option value="solid_light">Solid white (intentional)</option><option value="solid_dark">Solid black (intentional)</option></select></label></div>

      <p className="text-sm text-muted-foreground">Realistic nature, lifestyle, technology and product visuals use approved uploaded media. Add screenshots or licensed footage in <Link className="underline" href="/admin/affiliate-agent/media">Affiliate Agent Media</Link>. When a scene has no media, the renderer uses a moving abstract background.</p>

      <div className="space-y-4">{plans.map(plan => <fieldset key={plan.id} className="grid gap-3 rounded-lg border p-4 sm:grid-cols-2"><legend className="px-1 font-semibold">{plan.title}</legend><label className="sm:col-span-2">Spoken scene narration<Textarea className="mt-2" value={narration[plans.findIndex(item => item.id === plan.id)]} onChange={e => updateNarration(plans.findIndex(item => item.id === plan.id), e.target.value)} required maxLength={500} placeholder={plan.prompt} /></label><label>Headline (large)<Input className="mt-2" name={`${plan.id}_headline`} required maxLength={140} onChange={e => setSceneCopy(old => ({...old, [plan.id]: {...(old[plan.id] || {headline:"", body:""}), headline:e.target.value}}))} placeholder="Short, direct headline" /></label><label>Body copy (smaller)<Input className="mt-2" name={`${plan.id}_body`} maxLength={500} onChange={e => setSceneCopy(old => ({...old, [plan.id]: {...(old[plan.id] || {headline:"", body:""}), body:e.target.value}}))} placeholder="Optional supporting point" /></label><label>Background media<select className={selectClass} aria-label={`${plan.id} background media`} value={selectedMedia[plan.id]?.id || ""} onChange={e => { const asset = [...uploadedMedia, ...(media.data || [])].find(m => m.id === Number(e.target.value)); setSelectedMedia(previous => { const next = { ...previous }; if (asset) next[plan.id] = asset; else delete next[plan.id]; return next; }); }}><option value="">Auto-select relevant media</option>{[...uploadedMedia, ...(media.data || [])].filter((m, index, all) => (m.mime_type.startsWith("image/") || m.mime_type === "video/mp4") && all.findIndex(candidate => candidate.id === m.id) === index).map(m => <option key={m.id} value={m.id}>{m.name} · {m.mime_type === "video/mp4" ? "video" : "image"}</option>)}</select></label><label>Upload scene image/video<input className={`${fileUploadClass} mt-2`} type="file" accept="image/jpeg,image/png,image/webp,video/mp4" disabled={!limits.data || uploadingScene === plan.id} onChange={e => { const file = e.target.files?.[0]; e.currentTarget.value = ""; if (file) void uploadSceneMedia(plan.id, file); }} /></label><div className="grid gap-3 sm:col-span-2 sm:grid-cols-2 lg:grid-cols-4"><label>Headline size (68–104 px)<Input className="mt-2" name={`${plan.id}_headline_size`} type="number" min={68} max={104} placeholder="Auto · 80–90" /></label><label>Body size (36–60 px)<Input className="mt-2" name={`${plan.id}_body_size`} type="number" min={36} max={60} placeholder="Auto · 40–50" /></label><label className="flex items-center gap-2 sm:col-span-2"><input type="checkbox" checked={Boolean(colorOverrides[plan.id])} onChange={e => setColorOverrides(previous => ({ ...previous, [plan.id]: e.target.checked }))} />Customize text / CTA colors</label><label>Headline color<Input className="mt-2 h-11" name={`${plan.id}_headline_color`} type="color" disabled={!colorOverrides[plan.id]} defaultValue="#ffffff" /></label><label>Body color<Input className="mt-2 h-11" name={`${plan.id}_body_color`} type="color" disabled={!colorOverrides[plan.id]} defaultValue="#ffffff" /></label><label>CTA text color<Input className="mt-2 h-11" name={`${plan.id}_cta_text_color`} type="color" disabled={!colorOverrides[plan.id]} defaultValue="#ffffff" /></label><label>CTA background color<Input className="mt-2 h-11" name={`${plan.id}_cta_background_color`} type="color" disabled={!colorOverrides[plan.id]} defaultValue="#101820" /></label><label>CTA border color<Input className="mt-2 h-11" name={`${plan.id}_cta_border_color`} type="color" disabled={!colorOverrides[plan.id]} defaultValue="#16805c" /></label></div><div className="sm:col-span-2 grid gap-3 sm:grid-cols-4"><label>Font weight<select className={selectClass} name={`${plan.id}_font_weight`} defaultValue="bold">{["regular","medium","semibold","bold","extra_bold"].map(x => <option key={x} value={x}>{x.replaceAll("_", " ")}</option>)}</select></label><label>Outline<select className={selectClass} name={`${plan.id}_stroke`} defaultValue="medium">{["none","thin","medium","thick"].map(x => <option key={x}>{x}</option>)}</select></label><label>Outline color<Input className="mt-2 h-11" name={`${plan.id}_stroke_color`} type="color" defaultValue="#101820" /></label><label>Shadow<select className={selectClass} name={`${plan.id}_shadow`} defaultValue="soft">{["none","soft","medium","strong"].map(x => <option key={x}>{x}</option>)}</select></label></div><label>Reusable overlay<select className={selectClass} name={`${plan.id}_template`} defaultValue={plan.template}>{templates.map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label><label>Text animation<select className={selectClass} name={`${plan.id}_animation`} defaultValue={plan.animation}>{["fade_up", "slide_in", "scale_in", "masked_reveal", "staggered", "typewriter", "cursor_reveal", "fade_in", "fade_in_out", "slide_up", "slide_left", "slide_right", "scale_pop", "word_stagger"].map(x => <option key={x} value={x}>{x.replaceAll("_", " ")}</option>)}</select></label><label>Layout<select className={selectClass} name={`${plan.id}_layout`} defaultValue={plan.layout}>{["left", "center", "split"].map(x => <option key={x}>{x}</option>)}</select></label></fieldset>)}</div>

      <label className="block">Video Script Summary<Textarea className="mt-2 min-h-64 font-mono" aria-label="Video Script Summary" value={summary} onChange={e => { setSummary(e.target.value); const parsed = parseScriptSummary(e.target.value, plans); if (parsed) setNarration(parsed); }} /><span className="mt-1 block text-xs text-muted-foreground">Edit narration under HOOK, CONTENT 1 and FINISHING / CTA. Scene narration and the generated voice use this same text.</span></label>

      <p className="text-xs text-muted-foreground">{limits.data ? `Uploads: images max ${limits.data.image} MB · videos max ${limits.data.video} MB (JPEG, PNG, WebP, MP4).` : "Loading upload limits…"} Default CTA has a lightly transparent background, primary Dewdora border, and readable white text.</p>

      <label className="block">CTA (optional wording)<Input className="mt-2" name="cta" onChange={e => setCtaPreview(e.target.value)} defaultValue="Try the tool using the link in the description." maxLength={500} /></label>

      <label className="block">Affiliate disclosure<Textarea className="mt-2" name="disclosure" defaultValue="Affiliate link: I may earn a commission at no extra cost to you." required maxLength={500} /></label>

      <details><summary className="cursor-pointer">Voice override (advanced) — use the configured default otherwise</summary><label className="mt-3 flex items-center gap-2"><input type="checkbox" checked={overrideVoice} onChange={e => setOverrideVoice(e.target.checked)} />Override default voice</label>{overrideVoice && <label className="mt-3 block">ElevenLabs voice<select className={selectClass} name="voice_id"><option value="">Use default voice</option>{voices.data?.map(v => <option key={v.voice_id} value={v.voice_id}>{v.name}</option>)}</select>{voices.isError && <span role="alert">Could not load voices; check ElevenLabs settings.</span>}</label>}</details>

      <p className="text-sm text-muted-foreground">Platform metadata stays editable during review. Every revision creates a new version and requires final approval again.</p>

      <Button type="submit" disabled={busy}>Generate master video for review</Button>

    </form></CardContent></Card>

  </>;

}
