"use client";
import { useEffect, useState } from "react";
import api from "@/lib/axios";
import { toast } from "sonner";

const entries = [
  ["latest_products", "Latest Affiliate Products"], ["popular_products", "Popular Affiliate Products"],
  ["hero_banner", "Hero Banner"], ["featured_categories", "Featured Categories"],
  ["featured_products", "Featured Products"], ["featured_brands", "Featured Brands"],
  ["latest_reviews", "Latest Reviews"], ["buying_guides", "Buying Guides / How-tos"],
  ["popular_posts", "Popular Reads"], ["newsletter", "Newsletter"],
] as const;
type Section = typeof entries[number][0];
type Visibility = Record<Section, boolean>;
const defaults = Object.fromEntries(entries.map(([key]) => [key, true])) as Visibility;

export default function HomepageSettingsPage() {
  const [sections, setSections] = useState<Visibility>(defaults);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  useEffect(() => {
    api.get("/admin/settings")
      .then(response => setSections({ ...defaults, ...response.data.data.homepage_sections }))
      .catch(() => toast.error("Could not load homepage settings"))
      .finally(() => setLoading(false));
  }, []);
  async function save() {
    setSaving(true);
    try { await api.put("/admin/settings/homepage", { homepage_sections: sections }); toast.success("Homepage settings saved"); }
    catch { toast.error("Could not save homepage settings"); }
    finally { setSaving(false); }
  }
  return <div className="max-w-3xl"><h1 className="text-3xl font-bold">Homepage sections</h1>
    <p className="mt-2 text-muted-foreground">Choose what visitors see. Enabled sections follow the homepage order: latest products, popular products, hero, then the remaining content.</p>
    {loading ? <p className="mt-8">Loading homepage settings…</p> : <>
      <div className="mt-8 divide-y rounded-2xl border bg-background shadow-sm">{entries.map(([key, label]) =>
        <label key={key} className="flex cursor-pointer items-center justify-between gap-5 px-5 py-4 hover:bg-muted/40">
          <span className="font-medium">{label}</span>
          <input type="checkbox" checked={sections[key]} onChange={event => setSections(current => ({ ...current, [key]: event.target.checked }))}
            className="h-5 w-5 cursor-pointer accent-emerald-700" aria-label={`Show ${label}`} />
        </label>)}
      </div>
      <button type="button" disabled={saving} onClick={save} className="mt-6 cursor-pointer rounded-xl bg-primary px-6 py-3 font-bold text-primary-foreground hover:opacity-90 disabled:cursor-wait disabled:opacity-60">{saving ? "Saving…" : "Save homepage settings"}</button>
    </>}
  </div>;
}
