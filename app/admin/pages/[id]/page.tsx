"use client";
import { use, type Usable } from "react";
import { useQuery } from "@tanstack/react-query";
import PageEditor from "@/components/admin/pages/page-editor";
import { getAdminPage } from "@/services/admin-pages";
export default function Page({ params }: { params: Usable<{ id: string }> }) {
  const { id } = use(params); const pageId = Number(id);
  const q = useQuery({ queryKey: ["admin-page", pageId], queryFn: () => getAdminPage(pageId), enabled: Number.isInteger(pageId) && pageId > 0 });
  if (q.isPending) return <p>Loading page…</p>;
  if (q.isError || !q.data) return <p role="alert">Could not load page.</p>;
  return <PageEditor key={q.data.id} page={q.data} />;
}
