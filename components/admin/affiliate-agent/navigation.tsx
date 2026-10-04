"use client";
import Link from "next/link";
import type { Route } from "next";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/utils";
const tabs = [["Overview", ""], ["Review Queue", "/review"], ["Content & History", "/contents"], ["Media", "/media"], ["Future Preferences", "/preferences"], ["Setup", "/setup"], ["Affiliate Sources", "/sources"], ["Campaigns", "/campaigns"], ["Social Accounts", "/social-accounts"], ["Automation", "/automation"], ["Calendar", "/calendar"], ["Publishing", "/publishing"], ["Settings", "/settings"]];
export default function AgentNavigation() {
  const pathname = usePathname();
  return <nav aria-label="Affiliate Agent" className="mb-7 flex flex-wrap gap-2">{tabs.map(([label, path]) => {
    const href = `/admin/affiliate-agent${path}`;
    const active = pathname === href || (!!path && pathname.startsWith(`${href}/`));
    return <Link key={href} href={href as Route} className={cn("rounded-lg border px-4 py-2 text-sm", active ? "bg-primary text-primary-foreground" : "bg-background hover:bg-muted")}>{label}</Link>;
  })}<Link href="/admin/brands" className="rounded-lg border bg-background px-4 py-2 text-sm">Brands</Link><Link href="/admin/products" className="rounded-lg border bg-background px-4 py-2 text-sm">Products</Link></nav>;
}
