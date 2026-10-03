"use client";
import { useEffect, useState, type FormEvent } from "react";
import { listResources, saveResource, deleteResource, type ResourceEndpoint, type ResourceRecord } from "@/services/admin-resources";
import { toast } from "sonner";
import { PageSizeSelect, type PageSize } from "@/components/admin/page-size";
import type { PaginationMeta } from "@/types/api";

type Field = {
  key: string;
  label: string;
  kind?:
    "text" | "textarea" | "number" | "url" | "checkbox" | "select" | "array";
  required?: boolean;
  options?: { label: string; value: string }[];
};
type Config = { title: string; endpoint: ResourceEndpoint; fields: Field[] };
const name: Field = { key: "name", label: "Name", required: true };
const slug: Field = { key: "slug", label: "Slug" };
const resources: Record<string, Config> = {
  categories: {
    title: "Categories",
    endpoint: "categories",
    fields: [
      name,
      slug,
      { key: "description", label: "Description", kind: "textarea" },
      { key: "image", label: "Image path" },
      { key: "sort_order", label: "Sort order", kind: "number" },
      { key: "status", label: "Active", kind: "checkbox" },
    ],
  },
  tags: { title: "Tags", endpoint: "tags", fields: [name, slug] },
  brands: {
    title: "Brands",
    endpoint: "brands",
    fields: [
      name,
      slug,
      { key: "description", label: "Description", kind: "textarea" },
      { key: "website", label: "Website", kind: "url" },
      { key: "logo", label: "Logo path" },
      { key: "status", label: "Active", kind: "checkbox" },
    ],
  },
  networks: {
    title: "Affiliate networks",
    endpoint: "affiliate-networks",
    fields: [
      name,
      slug,
      { key: "description", label: "Description", kind: "textarea" },
      { key: "website", label: "Website", kind: "url" },
      { key: "status", label: "Active", kind: "checkbox" },
    ],
  },
  roles: {
    title: "Roles",
    endpoint: "roles",
    fields: [
      name,
      { ...slug, required: true },
      { key: "description", label: "Description", kind: "textarea" },
      { key: "status", label: "Active", kind: "checkbox" },
    ],
  },

};
type RecordItem = ResourceRecord;
function errorMessage(error: unknown) {
  if (typeof error === "object" && error && "response" in error) {
    const r = (
      error as {
        response?: {
          data?: { message?: string; errors?: Record<string, string[]> };
        };
      }
    ).response;
    return (
      Object.values(r?.data?.errors || {})
        .flat()
        .join(" ") ||
      r?.data?.message ||
      "Request failed"
    );
  }
  return "Request failed";
}
function serialize(form: HTMLFormElement, fields: Field[]) {
  const data = new FormData(form);
  return Object.fromEntries(
    fields.map((field) => {
      const raw = data.get(field.key);
      return [
        field.key,
        field.kind === "checkbox"
          ? data.has(field.key)
          : field.kind === "number"
            ? raw === "" || raw == null
              ? field.key === "sort_order" ? 0 : null
              : Number(raw)
            : field.kind === "array"
              ? String(raw || "")
                  .split("\n")
                  .map((x) => x.trim())
                  .filter(Boolean)
              : raw || null,
      ];
    }),
  );
}
function Editor({
  config,
  initial,
  onSaved,
  onCancel,
}: {
  config: Config;
  initial?: RecordItem;
  onSaved: () => void;
  onCancel: () => void;
}) {
  const [busy, setBusy] = useState(false);
  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    try {
      const values = serialize(event.currentTarget, config.fields);
      await saveResource(config.endpoint, values, initial?.id);
      toast.success("Saved successfully");
      onSaved();
    } catch (error) {
      toast.error(errorMessage(error));
    } finally {
      setBusy(false);
    }
  }
  return (
    <form
      onSubmit={save}
      className="mt-6 grid max-w-3xl gap-5 rounded-xl border bg-background p-6 md:grid-cols-2"
    >
      {config.fields.map((field) => (
        <label
          key={field.key}
          className={`text-sm font-semibold ${field.kind === "textarea" ? "md:col-span-2" : ""}`}
        >
          {field.label}
          {field.kind === "checkbox" ? (
            <input
              name={field.key}
              type="checkbox"
              defaultChecked={initial ? Boolean(initial[field.key]) : true}
              className="ml-3"
            />
          ) : field.kind === "textarea" || field.kind === "array" ? (
            <textarea
              name={field.key}
              defaultValue={
                field.kind === "array" && Array.isArray(initial?.[field.key])
                  ? (initial?.[field.key] as string[]).join("\n")
                  : String(initial?.[field.key] || "")
              }
              required={field.required}
              rows={5}
              className="mt-2 w-full rounded-lg border p-3"
            />
          ) : (
            <input
              name={field.key}
              type={field.kind || "text"}
              defaultValue={String(
                field.key === "featured_image"
                  ? initial?.featured_image_path || ""
                  : field.key === "image"
                    ? initial?.image_path || ""
                    : field.key === "logo"
                      ? initial?.logo_path || ""
                      : initial?.[field.key] ?? "",
              )}
              required={field.required}
              step={field.kind === "number" ? "any" : undefined}
              className="mt-2 w-full rounded-lg border p-3"
            />
          )}
        </label>
      ))}
      <div className="flex gap-3 md:col-span-2">
        <button
          disabled={busy}
          className="rounded-lg bg-[#165e46] px-5 py-2 text-white disabled:opacity-50"
        >
          {busy ? "Saving…" : "Save"}
        </button>
        <button
          type="button"
          onClick={onCancel}
          className="rounded-lg border px-5 py-2"
        >
          Cancel
        </button>
      </div>
    </form>
  );
}
export function ResourcePage({ resource }: { resource: string }) {
  const config = resources[resource];
  const [items, setItems] = useState<RecordItem[]>([]);
  const [meta, setMeta] = useState<PaginationMeta>();
  const [page, setPage] = useState(1);
  const [size, setSize] = useState<PageSize>(20);
  const [search, setSearch] = useState("");
  const [editing, setEditing] = useState<RecordItem | "new" | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [revision, setRevision] = useState(0);
  useEffect(() => {
    if (!config) return;
    let active = true;
    listResources(config.endpoint, { page, search, per_page: size })
      .then((r) => {
        if (active) {
          setItems(r.items);
          setMeta(r.meta);
          setError(null);
        }
      })
      .catch((error) => {
        if (active) setError(errorMessage(error));
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [config, page, search, size, editing, revision]);
  if (!config) return <p>Resource not found.</p>;
  async function remove(item: RecordItem) {
    if (!window.confirm(`Delete ${item.name || item.id}?`)) return;
    try {
      await deleteResource(config.endpoint, item.id);
      toast.success("Deleted");
      setEditing(null);
      setItems((current) => current.filter((x) => x.id !== item.id));
      if (items.length === 1 && page > 1) setPage(page - 1);
      setRevision(value => value + 1);
    } catch (error) {
      toast.error(errorMessage(error));
    }
  }
  return (
    <div>
      <div className="flex items-center justify-between gap-4">
        <h1 className="text-3xl font-bold">{config.title}</h1>
        <button
          onClick={() => setEditing("new")}
          className="rounded-lg bg-[#165e46] px-5 py-2 text-white"
        >
          Add new
        </button>
      </div>
      <input
        type="search"
        value={search}
        onChange={(e) => {
          setSearch(e.target.value);
          setPage(1);
        }}
        placeholder={`Search ${config.title.toLowerCase()}`}
        className="mt-6 w-full max-w-sm rounded-lg border bg-background p-3"
      />
      <div className="mt-4"><PageSizeSelect value={size} onChange={value => { setSize(value); setPage(1); }} /></div>
      {editing && (
        <Editor
          key={editing === "new" ? "new" : editing.id}
          config={config}
          initial={editing === "new" ? undefined : editing}
          onSaved={() => setEditing(null)}
          onCancel={() => setEditing(null)}
        />
      )}
      <div className="mt-6 overflow-x-auto rounded-xl border bg-background">
        <table className="w-full text-left text-sm">
          <thead className="bg-muted/50">
            <tr>
              <th className="p-4">Name</th>
              <th className="p-4">Slug</th>
              <th className="p-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            {items.map((item) => (
              <tr key={item.id} className="border-t">
                <td className="p-4 font-semibold">{item.name}</td>
                <td className="p-4">{item.slug}</td>
                <td className="p-4">
                  <button
                    onClick={() => setEditing(item)}
                    className="mr-4 text-[#165e46]"
                  >
                    Edit
                  </button>
                  <button onClick={() => remove(item)} className="text-red-600">
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {loading && <p className="p-4">Loading…</p>}
        {error && <p role="alert" className="p-4 text-red-700">{error} <button onClick={() => setRevision(value => value + 1)} className="underline">Retry</button></p>}
        {!loading && !error && !items.length && <p className="p-4">No records found.</p>}
      </div>
      {meta && meta.last_page > 1 && (
        <div className="mt-5 flex items-center gap-3">
          <button
            disabled={page <= 1}
            onClick={() => setPage(page - 1)}
            className="rounded border px-3 py-2 disabled:opacity-40"
          >
            Previous
          </button>
          <span>
            {page} / {meta.last_page}
          </span>
          <button
            disabled={page >= meta.last_page}
            onClick={() => setPage(page + 1)}
            className="rounded border px-3 py-2 disabled:opacity-40"
          >
            Next
          </button>
        </div>
      )}
    </div>
  );
}
