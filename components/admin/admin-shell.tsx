"use client";
import { routes } from "@/lib/routes";
import { useEffect } from "react";
import { useRouter } from "next/navigation";
import Sidebar from "@/components/admin/sidebar";
import AdminHeader from "@/components/admin/header";
import { useAuth } from "@/hooks/use-auth";
export default function AdminShell({ children }: { children: React.ReactNode }) {
 const router = useRouter(); const { authenticated, loading, user } = useAuth();
 useEffect(() => { if (!loading && !authenticated) router.replace(routes.login); }, [authenticated, loading, router]);
 if (loading) return <div className="flex min-h-screen items-center justify-center">Loading dashboard…</div>;
 if (!authenticated) return null;
 if (user?.role?.slug !== "admin") return <div className="grid min-h-screen place-items-center p-6"><div className="max-w-md text-center"><h1 className="text-2xl font-bold">Administrator access required</h1><p className="mt-3 text-muted-foreground">Your account is signed in, but this CMS is restricted to administrators.</p></div></div>;
 return <div className="admin-area flex min-h-screen bg-muted/30"><Sidebar /><div className="flex min-w-0 flex-1 flex-col"><AdminHeader /><main className="p-4 sm:p-6">{children}</main></div></div>;
}
