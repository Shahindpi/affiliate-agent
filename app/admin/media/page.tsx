"use client";
import { useEffect, useState, type FormEvent } from "react";
import Image from "next/image";
import { PageSizeSelect, type PageSize } from "@/components/admin/page-size";
import api from "@/lib/axios";
import { toast } from "sonner";
type Media = { name: string; path: string; url: string; size: number };
export default function Page() {
  const [items, setItems] = useState<Media[]>([]);
  const [page, setPage] = useState(1);
  const [size, setSize] = useState<PageSize>(20);
  const [total, setTotal] = useState(1);
  const [revision, setRevision] = useState(0);
  const [copiedPath, setCopiedPath] = useState<string | null>(null);
  useEffect(() => {
    if (!copiedPath) return;
    const timeout = window.setTimeout(() => setCopiedPath(null), 2200);
    return () => window.clearTimeout(timeout);
  }, [copiedPath]);
  useEffect(() => {
    api
      .get("/admin/media", { params: { page, per_page: size } })
      .then((r) => {
        setItems(r.data.data?.media || []);
        setTotal(r.data.data?.pagination?.last_page || 1);
      })
      .catch(() => toast.error("Could not load media"));
  }, [page, size, revision]);
  async function upload(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const form = e.currentTarget;
    try {
      await api.post("/admin/media/upload", new FormData(form), {
        headers: { "Content-Type": "multipart/form-data" },
      });
      toast.success("Image uploaded");
      form.reset();
      setRevision((x) => x + 1);
    } catch {
      toast.error("Could not upload image");
    }
  }
  async function remove(item: Media) {
    if (!window.confirm(`Delete ${item.name}?`)) return;
    try {
      await api.delete("/admin/media/delete", { data: { path: item.path } });
      toast.success("Image deleted");
      setRevision((x) => x + 1);
    } catch {
      toast.error("Image could not be deleted; it may be in use");
    }
  }
  async function copyPath(item: Media) {
    try {
      await navigator.clipboard.writeText(item.path);
      setCopiedPath(item.path);
      toast.success("Copied");
    } catch { setCopiedPath(null); toast.error("Could not copy image path. Check clipboard permission."); }
  }
  return (
    <div>
      <h1 className="text-3xl font-bold">Media library</h1>
      <form onSubmit={upload} className="mt-6 flex flex-wrap gap-3">
        <input type="file" name="image" accept="image/*" required />
        <button className="cursor-pointer rounded-lg bg-primary px-5 py-2 text-primary-foreground hover:opacity-90 focus-visible:outline-2 focus-visible:outline-ring">
          Upload image
        </button>
      </form>
      <div className="mt-4"><PageSizeSelect value={size} onChange={value => { setSize(value); setPage(1); }} /></div>
      <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        {items.map((item) => (
          <div
            key={item.path}
            className="overflow-hidden rounded-xl border bg-background shadow-sm transition-shadow hover:shadow-md"
          >
              <Image
                unoptimized
                width={640}
                height={320}
                src={item.url}
              alt={item.name}
              className="h-40 w-full object-contain"
            />
            <div className="p-4 text-sm">
              <p className="truncate font-semibold">{item.name}</p>
              <button type="button" onClick={() => void copyPath(item)} className={`mt-3 mr-3 cursor-pointer rounded-lg border px-3 py-2 font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-ring ${copiedPath === item.path ? "border-emerald-600 bg-emerald-600 text-white" : "border-border bg-background text-foreground hover:bg-muted"}`}>
                {copiedPath === item.path ? "✓ Copied" : "Copy path"}
              </button>
              <button type="button" onClick={() => void remove(item)} className="cursor-pointer rounded-lg px-3 py-2 text-destructive hover:bg-destructive/10 focus-visible:outline-2 focus-visible:outline-ring">
                Delete
              </button>
            </div>
          </div>
        ))}
      </div>
      <div className="mt-6 flex gap-3">
        <button
          disabled={page <= 1}
          onClick={() => setPage(page - 1)}
          className="rounded border px-3 py-2"
        >
          Previous
        </button>
        <span>
          {page} / {total}
        </span>
        <button
          disabled={page >= total}
          onClick={() => setPage(page + 1)}
          className="rounded border px-3 py-2"
        >
          Next
        </button>
      </div>
    </div>
  );
}
