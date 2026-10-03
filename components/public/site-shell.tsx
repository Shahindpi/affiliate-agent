import { routes, publicNavigation } from "@/lib/routes";
import Link from "next/link";
import type { ReactNode } from "react";
import Image from "next/image";
import { safePublicGet } from "@/lib/public-api";
import type { ApiResponse } from "@/types/api";
import { unstable_rethrow } from "next/navigation";
import { MobileNav } from "@/components/public/mobile-nav";


export async function SiteShell({ children }: { children: ReactNode }) {
  let settings: { logo?: string | null } = {};
  try {
    const response = await safePublicGet<ApiResponse<{ logo?: string | null }>>("settings", { success: false, data: {} });
    settings = response.data || {};
  } catch (error) {
    unstable_rethrow(error);
    console.error("Dewdora public settings API failed", error);
  }
  return <div className="min-h-screen bg-[#f8f8f2] text-[#18352d]">
    <header className="sticky top-0 z-50 bg-white/95 backdrop-blur"><div className="relative mx-auto flex max-w-6xl items-center justify-between gap-5 px-6 py-2">
      <Link href={routes.home} aria-label="Dewdora homepage"><Image unoptimized src={settings.logo || "/dewdora-logo.svg"} alt="Dewdora" width={186} height={48} className="h-12 w-auto object-contain" /></Link>
      <nav aria-label="Main navigation" className="hidden flex-wrap gap-5 text-sm font-semibold md:flex">{publicNavigation.map(({ label, href }) => <Link key={href} href={href} className="hover:text-[#2c9873]">{label}</Link>)}</nav>
      <MobileNav />
    </div></header>
    <main className="mx-auto max-w-6xl px-6 py-6">{children}</main>
    <footer className="mt-16 border-t border-[#dce6d9] bg-white"><div className="mx-auto max-w-6xl px-6 py-8 text-sm"><p className="max-w-3xl text-[#567069]"><strong>Affiliate disclosure:</strong> Dewdora may earn a commission when you purchase through eligible links, at no additional cost to you. Recommendations remain editorially selected.</p><div className="mt-6 flex flex-wrap justify-between gap-4"><span>© {new Date().getFullYear()} Dewdora</span><div className="flex gap-5"><Link href={routes.contact}>Contact</Link><Link href={routes.login}>Admin</Link></div></div></div></footer>
  </div>;
}
