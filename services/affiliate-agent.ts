import api from "@/lib/axios";
import type { ApiResponse } from "@/types/api";
import type { AgentContent, AgentMedia, Feedback, Overview, Preference, PreferencePayload, Snapshot } from "@/types/affiliate-agent";
const root = "/admin/affiliate-agent";
export async function agentOverview() { return (await api.get<ApiResponse<Overview>>(`${root}/overview`)).data.data; }
export async function agentContents(params: { status?: string; search?: string; page: number }) { return (await api.get<ApiResponse<{ data: AgentContent[]; current_page: number; last_page: number; total: number }>>(`${root}/contents`, { params })).data.data; }
export async function agentContent(id: number) { return (await api.get<ApiResponse<AgentContent>>(`${root}/contents/${id}`)).data.data; }
export async function createAgentContent(payload: { title: string; brand_id: number | null; affiliate_product_id: number | null; snapshot: Snapshot }) { return (await api.post<ApiResponse<AgentContent>>(`${root}/contents`, payload)).data.data; }
export async function reviseContent(id: number, expected: number, feedback: string, target: string, patch?: Partial<Snapshot>) { return (await api.post<ApiResponse<Feedback>>(`${root}/contents/${id}/revisions`, { expected_version_id: expected, feedback, target, ...(patch ? { patch } : {}) })).data.data; }
export async function contentAction(id: number, action: "approve" | "reject" | "restore" | "publications", expected: number, extra: Record<string, unknown> = {}) { return (await api.post(`${root}/contents/${id}/${action}`, { expected_version_id: expected, ...extra })).data.data; }
export async function setContentLocks(id: number, expected: number, locks: string[]) { return (await api.put(`${root}/contents/${id}/locks`, { expected_version_id: expected, locks })).data.data; }
export async function agentPreferences() { return (await api.get<ApiResponse<Preference[]>>(`${root}/preferences`)).data.data; }
export async function savePreference(payload: PreferencePayload, id?: number) { return (await (id ? api.put(`${root}/preferences/${id}`, payload) : api.post(`${root}/preferences`, payload))).data.data; }
export async function disablePreference(id: number) { await api.delete(`${root}/preferences/${id}`); }
export async function agentMedia() { return (await api.get<ApiResponse<AgentMedia[]>>(`${root}/media`)).data.data; }
export async function uploadAgentMedia(form: FormData) { return (await api.post(`${root}/media`, form, { headers: { "Content-Type": "multipart/form-data" } })).data.data; }
export async function downloadMetadata(id: number, versionId: number, number: number) {
  const r = await api.get(`${root}/contents/${id}/export`, { params: { version_id: versionId }, responseType: "blob" });
  const url = URL.createObjectURL(r.data); const a = document.createElement("a"); a.href = url; a.download = `content-${id}-v${number}-metadata.json`; a.click(); URL.revokeObjectURL(url);
}
export async function confirmPublication(id: number, external_id: string) { await api.post(`${root}/publications/${id}/confirm`, { external_id }); }
export async function downloadAsset(url: string, name: string) {
  const r = await fetch(url); if (!r.ok) throw new Error("Preview link expired; refresh the content page.");
  const blob = URL.createObjectURL(await r.blob()); const a = document.createElement("a"); a.href = blob; a.download = name; a.click(); URL.revokeObjectURL(blob);
}
