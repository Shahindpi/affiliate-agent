"use client";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import PageTitle from "@/components/admin/page-title";
import PinterestDestinations from "@/components/admin/affiliate-agent/pinterest-destinations";
import { getSocialAccounts, connectSocial, updateSocial, disconnectSocial, testSocial } from "@/services/agent-setup";
import { apiErrorMessage } from "@/lib/api-error";

const platforms = ["pinterest", "instagram", "tiktok", "facebook", "youtube"];
export default function Page() {
  const client = useQueryClient();
  const q = useQuery({ queryKey: ["agent-social"], queryFn: getSocialAccounts });
  async function run(task: () => Promise<unknown>, message: string) {
    try { await task(); toast.success(message); await client.invalidateQueries({ queryKey: ["agent-social"] }); }
    catch (e) { toast.error(apiErrorMessage(e, "Action failed.")); }
  }
  async function connect(platform: string) {
    try { const r = await connectSocial(["instagram", "facebook"].includes(platform) ? "meta" : platform); window.location.assign(r.authorization_url); }
    catch (e) { toast.error(apiErrorMessage(e, "Connect failed.")); }
  }
  return <><PageTitle title="Social accounts" description="Authorize platforms with official consent. Tokens are stored encrypted in Laravel and never shown here." />
    {q.isPending && <p>Loading accounts…</p>}{q.isError && <p role="alert">Could not load social accounts.</p>}
    <div className="grid gap-4 lg:grid-cols-2">{q.data && platforms.map(platform => {
      const accounts = q.data.accounts.filter(a => a.platform === platform);
      const configured = q.data.configured[["instagram", "facebook"].includes(platform) ? "meta" : platform];
      return <section key={platform} className="rounded-lg border bg-background p-5">
        <div className="flex justify-between gap-4"><h2 className="text-lg font-semibold capitalize">{platform}</h2><span>{!configured ? "NOT_CONFIGURED" : accounts.length ? accounts.map(a => a.status).join(", ") : "AUTHORIZATION_REQUIRED"}</span></div>
        {!configured && <p className="mt-2 text-sm">Add the developer app credentials in Laravel, then reconnect. Manual export stays available.</p>}
        <button className="mt-3 rounded border px-3 py-2 text-sm" disabled={!configured} onClick={() => void connect(platform)}>{accounts.length ? "Reconnect / add" : "Connect"}</button>
        {accounts.map(a => <div key={a.id} className="mt-4 rounded border p-3 text-sm">
          <strong>{a.name}</strong> · {a.username || a.external_id}<p>Token: {a.expires_at ? new Date(a.expires_at) < new Date() ? "TOKEN_EXPIRED" : `expires ${new Date(a.expires_at).toLocaleString()}` : "Provider managed"}</p>
          <p>Granted scopes: {a.scopes?.join(", ") || "Not returned by provider"}</p><p>Verified: {a.last_verified_at ? new Date(a.last_verified_at).toLocaleString() : "Never"}</p>
          <label>API app review status <select className="rounded border p-2" value={a.api_review_status} onChange={e => void run(() => updateSocial(a.id, { api_review_status: e.target.value }), "Review status saved.")}><option value="UNKNOWN">Unknown</option><option value="API_REVIEW_REQUIRED">Review required</option><option value="APPROVED">Approved by platform (confirm first)</option></select></label>
          {a.last_error && <p className="text-destructive">{a.last_error}</p>}
          {platform === "pinterest" && <><label className="mt-2 block">Public HTTPS cover image URL<input className="mt-1 w-full rounded border p-2" defaultValue={a.metadata?.cover_image_url || ""} onBlur={e => void run(() => updateSocial(a.id, { metadata: { cover_image_url: e.target.value } }), "Cover saved.")} /></label>{a.status === "CONNECTED" && <PinterestDestinations accountId={a.id} />}</>}
          <label className="mt-2 flex items-center gap-2"><input type="checkbox" checked={a.publishing_enabled} onChange={e => void run(() => updateSocial(a.id, { publishing_enabled: e.target.checked }), "Publishing preference saved.")} />Enable publishing</label>
          <div className="mt-2 flex gap-4"><button className="underline" onClick={() => void run(async () => { const r = await testSocial(a.id); if (!r.ok) throw new Error(r.message); }, "Account verified.")}>Test</button><button className="underline" onClick={() => void run(() => disconnectSocial(a.id), "Account disconnected.")}>Disconnect</button></div>
        </div>)}
      </section>;
    })}</div>
  </>;
}
