"use client";
import { routes } from "@/lib/routes";
import { useState } from "react";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { Eye, EyeOff, Loader2, ArrowRight } from "lucide-react";
import { useAuth } from "@/hooks/use-auth";
import Logo from "@/components/layout/logo";

const schema = z.object({ email: z.email("Enter a valid email address."), password: z.string().min(6, "Enter your password.") });
type LoginValues = z.infer<typeof schema>;

export default function LoginForm() {
  const router = useRouter();
  const { login } = useAuth();
  const [loading, setLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState("");
  const form = useForm<LoginValues>({ resolver: zodResolver(schema), defaultValues: { email: "", password: "" } });
  async function onSubmit(values: LoginValues) {
    setError(""); setLoading(true);
    try { await login(values.email, values.password); router.replace(routes.admin.dashboard); }
    catch (reason: unknown) { setError((reason as { response?: { data?: { message?: string } } })?.response?.data?.message || "Unable to sign in. Check your details and try again."); }
    finally { setLoading(false); }
  }
  return <main className="grid min-h-screen bg-background text-foreground lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
    <div className="relative hidden overflow-hidden bg-[#123e32] p-12 text-white lg:flex lg:flex-col lg:justify-between">
      <div className="absolute -right-32 top-1/4 size-96 rounded-full border border-white/15" />
      <div className="absolute -right-16 top-1/3 size-64 rounded-full bg-[#2c9873]/35 blur-3xl" />
      <p className="relative text-sm font-semibold uppercase tracking-[.2em] text-white/70">Dewdora workspace</p>
      <div className="relative max-w-lg"><p className="mb-5 text-sm font-semibold uppercase tracking-[.2em] text-[#a0dec6]">Your editorial home</p><h1 className="text-5xl font-black leading-tight">Create discoveries worth sharing.</h1><p className="mt-7 text-lg leading-8 text-white/75">Manage products, publish useful guides and understand what readers explore.</p></div>
      <p className="relative text-sm text-white/60">Thoughtful tools. Clear recommendations.</p>
    </div>
    <div className="flex min-h-screen items-center justify-center px-5 py-14 sm:px-10">
      <div className="w-full max-w-md"><div className="mb-12 flex justify-center"><Logo /></div>
        <p className="text-sm font-bold uppercase tracking-[.16em] text-emerald-700 dark:text-emerald-400">Administrator access</p>
        <h2 className="mt-3 text-4xl font-black tracking-tight">Welcome back</h2>
        <p className="mt-3 text-muted-foreground">Sign in to your Dewdora account.</p>
        <form onSubmit={form.handleSubmit(onSubmit)} className="mt-9 space-y-6" noValidate>
          <div><label htmlFor="email" className="mb-2 block text-sm font-semibold">Email address</label><input id="email" type="email" autoComplete="email" placeholder="you@example.com" {...form.register("email")} aria-invalid={!!form.formState.errors.email} className="w-full rounded-xl border bg-background px-4 py-3 text-foreground outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/15" />{form.formState.errors.email && <p className="mt-2 text-sm text-destructive">{form.formState.errors.email.message}</p>}</div>
          <div><label htmlFor="password" className="mb-2 block text-sm font-semibold">Password</label><div className="relative"><input id="password" type={showPassword ? "text" : "password"} autoComplete="current-password" {...form.register("password")} aria-invalid={!!form.formState.errors.password} className="w-full rounded-xl border bg-background px-4 py-3 pr-14 text-foreground outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/15" /><button type="button" onClick={() => setShowPassword(!showPassword)} aria-label={showPassword ? "Hide password" : "Show password"} className="absolute inset-y-0 right-0 flex w-12 cursor-pointer items-center justify-center text-muted-foreground hover:text-foreground">{showPassword ? <EyeOff size={19} /> : <Eye size={19} />}</button></div>{form.formState.errors.password && <p className="mt-2 text-sm text-destructive">{form.formState.errors.password.message}</p>}</div>
          {error && <p role="alert" className="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">{error}</p>}
          <button type="submit" disabled={loading} className="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-[#165e46] px-5 py-3.5 font-bold text-white shadow-sm transition hover:bg-[#0e4634] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-wait disabled:opacity-60">{loading ? <><Loader2 className="size-5 animate-spin" /> Signing in…</> : <>Sign In <ArrowRight className="size-5" /></>}</button>
        </form><p className="mt-9 text-center text-sm text-muted-foreground">Authorized Dewdora team members only.</p>
      </div>
    </div>
  </main>;
}
