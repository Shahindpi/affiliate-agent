"use client";
import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiErrorMessage } from "@/lib/api-error";
import { getPinterestBoards, getPinterestSections, getPinterestDestinations, savePinterestDestination, createPinterestBoard, createPinterestSection } from "@/services/agent-setup";

export default function PinterestDestinations({ accountId }: { accountId: number }) {
  const client = useQueryClient(); const [board, setBoard] = useState(""); const [section, setSection] = useState(""); const [scope, setScope] = useState("account"); const [scopeId, setScopeId] = useState("");
  const boards = useQuery({ queryKey: ["pinterest-boards", accountId], queryFn: () => getPinterestBoards(accountId) });
  const sections = useQuery({ queryKey: ["pinterest-sections", accountId, board], queryFn: () => getPinterestSections(accountId, board), enabled: !!board });
  const destinations = useQuery({ queryKey: ["pinterest-destinations", accountId], queryFn: () => getPinterestDestinations(accountId) });
  async function run(action: () => Promise<unknown>, success: string) { try { await action(); toast.success(success); await Promise.all([client.invalidateQueries({ queryKey: ["pinterest-boards", accountId] }), client.invalidateQueries({ queryKey: ["pinterest-sections", accountId] }), client.invalidateQueries({ queryKey: ["pinterest-destinations", accountId] })]); } catch (e) { toast.error(apiErrorMessage(e, "Pinterest request failed.")); } }
  return <div className="mt-3 space-y-3 rounded border p-3"><h3 className="font-semibold">Pinterest board and optional section</h3><p>Example: Board <strong>AI Tools</strong> → Section <strong>AI Voice &amp; Audio</strong>. Select actual IDs returned by Pinterest.</p>
    <button className="underline" onClick={() => void boards.refetch()}>Refresh Boards</button>
    {boards.isError && <p role="alert">Could not fetch boards. Check Pinterest authorization and boards:read.</p>}
    <div className="grid gap-2 sm:grid-cols-2"><label>Board<select className="w-full rounded border p-2" value={board} onChange={e => { setBoard(e.target.value); setSection(""); }}><option value="">Select board</option>{boards.data?.items.map(b => <option key={b.id} value={b.id}>{b.name} · {b.id}</option>)}</select></label><label>Section (optional)<select className="w-full rounded border p-2" value={section} onChange={e => setSection(e.target.value)}><option value="">Board only</option>{sections.data?.items.map(s => <option key={s.id} value={s.id}>{s.name} · {s.id}</option>)}</select></label></div>
    {board && <button className="underline" onClick={() => void sections.refetch()}>Refresh Sections</button>}
    <div className="flex flex-wrap gap-3"><button className="underline" onClick={() => { const name = window.prompt("New Pinterest board name"); if (name?.trim()) void run(() => createPinterestBoard(accountId, name.trim()), "Board created. Select it from the refreshed list."); }}>Create Board</button><button disabled={!board} className="underline disabled:opacity-50" onClick={() => { const name = window.prompt("New section in this board"); if (name?.trim()) void run(() => createPinterestSection(accountId, board, name.trim()), "Section created. Select it from the refreshed list."); }}>Create Section</button></div>
    <div className="grid gap-2 sm:grid-cols-2"><label>Destination for<select className="w-full rounded border p-2" value={scope} onChange={e => { setScope(e.target.value); setScopeId(""); }}><option value="account">Account default</option><option value="brand">Brand</option><option value="product">Product</option><option value="campaign">Campaign</option><option value="content">Content item</option></select></label>{scope !== "account" && <label>{scope} ID<input className="w-full rounded border p-2" type="number" min="1" value={scopeId} onChange={e => setScopeId(e.target.value)} /></label>}</div>
    <button disabled={!board || (scope !== "account" && !scopeId)} className="rounded bg-primary px-3 py-2 text-primary-foreground disabled:opacity-50" onClick={() => void run(() => savePinterestDestination(accountId, { scope_type: scope, scope_id: scope === "account" ? null : Number(scopeId), external_board_id: board, external_section_id: section || null }), "Pinterest destination saved.")}>Save destination</button>
    {destinations.data?.map(d => <p className="rounded bg-muted p-2" key={d.id}>{d.scope_type}{d.scope_id ? ` #${d.scope_id}` : " default"}: {d.external_board_name}{d.external_section_name ? ` → ${d.external_section_name}` : " (board only)"} · IDs {d.external_board_id}{d.external_section_id ? ` / ${d.external_section_id}` : ""}</p>)}
  </div>;
}
