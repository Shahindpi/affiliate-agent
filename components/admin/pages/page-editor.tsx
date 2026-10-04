"use client";
import { useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { toast } from "sonner";
import PageTitle from "@/components/admin/page-title";
import RichTextEditor from "@/components/admin/editor/rich-text-editor";
import { saveAdminPage, type CmsPage, type CmsPagePayload } from "@/services/admin-pages";
import { apiErrorMessage } from "@/lib/api-error";
import { routes } from "@/lib/routes";
const initial: CmsPagePayload = { title: "", slug: "", excerpt: "", content: "<p></p>", status: false, seo: { meta_title: "", meta_description: "" } };
export default function PageEditor({ page }: { page?: CmsPage }) {
  const router = useRouter(); const [busy, setBusy] = useState(false);
  const [form, setForm] = useState<CmsPagePayload>(page ? { title: page.title, slug: page.slug, excerpt: page.excerpt, content: page.content, status: page.status, seo: { meta_title: page.seo_meta?.meta_title || "", meta_description: page.seo_meta?.meta_description || "" } } : initial);
  async function submit(e: FormEvent) {
    e.preventDefault(); setBusy(true);
    try { const saved = await saveAdminPage(form, page?.id); toast.success("Page saved."); router.push(routes.admin.pages.edit(saved.id)); router.refresh(); }
    catch (err) { toast.error(apiErrorMessage(err, "Page could not be saved.")); }
    finally { setBusy(false); }
  }
  return <><PageTitle title={page ? `Edit ${page.title}` : "Create page"} description="Published page content and SEO fields are served from the Laravel CMS." />
    <form onSubmit={submit} className="max-w-4xl space-y-5 rounded-lg border bg-background p-5"><label className="block">Title<input className="mt-2 w-full rounded border p-2" required maxLength={255} value={form.title} onChange={e => setForm({ ...form, title: e.target.value })} /></label>
      <label className="block">Slug<input className="mt-2 w-full rounded border p-2" required pattern="[a-z0-9]+(-[a-z0-9]+)*" value={form.slug} onChange={e => setForm({ ...form, slug: e.target.value })} /></label>
      <label className="block">Excerpt<textarea className="mt-2 w-full rounded border p-2" value={form.excerpt || ""} onChange={e => setForm({ ...form, excerpt: e.target.value })} /></label>
      <div><p className="mb-2">Content</p><RichTextEditor value={form.content} onChange={content => setForm(current => ({ ...current, content }))} /></div>
      <label className="block">SEO title<input className="mt-2 w-full rounded border p-2" value={form.seo.meta_title || ""} onChange={e => setForm({ ...form, seo: { ...form.seo, meta_title: e.target.value } })} /></label>
      <label className="block">Meta description<textarea className="mt-2 w-full rounded border p-2" value={form.seo.meta_description || ""} onChange={e => setForm({ ...form, seo: { ...form.seo, meta_description: e.target.value } })} /></label>
      <label className="flex items-center gap-2"><input type="checkbox" checked={form.status} onChange={e => setForm({ ...form, status: e.target.checked })} />Published</label>
      <button className="rounded bg-primary px-4 py-2 text-primary-foreground" disabled={busy} type="submit">Save page</button>
    </form>
  </>;
}
