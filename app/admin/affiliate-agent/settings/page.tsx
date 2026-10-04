"use client";
import { useQuery } from "@tanstack/react-query";
import { toast } from "sonner";
import Link from "next/link";
import PageTitle from "@/components/admin/page-title";
import { getSetup, testAgentProvider } from "@/services/agent-setup";
export default function Page() {
  const q = useQuery({ queryKey: ["agent-setup"], queryFn: getSetup });
  return <><PageTitle title="Provider settings" description="Credentials stay on the Laravel server. Configure the environment, clear config, then test each integration." />
    {q.isPending && <p>Loading provider status…</p>}{q.isError && <p role="alert">Could not load provider status.</p>}
    <div className="grid gap-4 sm:grid-cols-2">{q.data && [["OpenAI", "OPENAI_API_KEY, AGENT_AI_PROVIDER=openai", "openai"], ["ElevenLabs", "ELEVENLABS_API_KEY, AGENT_VOICE_PROVIDER=elevenlabs", "elevenlabs"]].map(([name, env, key]) => <section key={name} className="rounded-lg border bg-background p-5"><h2 className="font-semibold">{name}</h2><p>Status: {q.data?.providers[key].status}</p><p className="mt-2 text-sm">Laravel <code>agent-backend/.env</code>: {env}</p><button className="mt-3 rounded border px-3 py-2" onClick={() => void testAgentProvider(key).then(r => { toast[r.ok ? "success" : "error"](r.message); void q.refetch(); }).catch(() => toast.error("Provider test failed."))}>Test Connection</button></section>)}</div>
    <p className="mt-6">OAuth app secrets are configured in Laravel <code>.env</code>. Follow <code>documentation/</code>, then use <Link className="underline" href="/admin/affiliate-agent/social-accounts">Social Accounts</Link> to authorize destinations. Run <code>php artisan config:clear</code> and restart queue workers after changing environment values.</p>
  </>;
}
