"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { routes } from "@/lib/routes";

import Logo from "@/components/layout/logo";
import { cn } from "@/lib/utils";

import {
  LayoutDashboard,
  ChartNoAxesCombined,
  PanelTop,
  FileText,
  FolderTree,
  Tags,
  ShoppingBag,
  Building2,
  BadgeDollarSign,
  ImageIcon,
  Mail,
  MessageSquare,
  Settings,
  Users,
  ShieldCheck,
} from "lucide-react";

const items = [
  { label: "Dashboard", href: routes.admin.dashboard, icon: LayoutDashboard },
  { label: "Affiliate analytics", href: routes.admin.analytics, icon: ChartNoAxesCombined },
  { label: "Posts", href: routes.admin.posts.index, icon: FileText },
  { label: "Hero banners", href: routes.admin.heroBanners, icon: PanelTop },
  { label: "Homepage sections", href: routes.admin.homepageSettings, icon: PanelTop },
  { label: "Categories", href: routes.admin.categories, icon: FolderTree },
  { label: "Tags", href: routes.admin.tags, icon: Tags },
  { label: "Products", href: routes.admin.products.index, icon: ShoppingBag },
  { label: "Brands", href: routes.admin.brands, icon: Building2 },
  { label: "Networks", href: routes.admin.networks, icon: BadgeDollarSign },
  { label: "Media", href: routes.admin.media, icon: ImageIcon },
  { label: "Subscribers", href: routes.admin.subscribers, icon: Mail },
  { label: "Comments", href: routes.admin.comments, icon: MessageSquare },
  { label: "Contacts", href: routes.admin.contacts, icon: Mail },
  { label: "Users", href: routes.admin.users.index, icon: Users },
  { label: "Roles", href: routes.admin.roles, icon: ShieldCheck },
  { label: "Profile", href: routes.admin.profile, icon: Settings },
  { label: "Settings", href: routes.admin.settings, icon: Settings },
] as const;

export default function Sidebar({ mobile = false }: { mobile?: boolean }) {
  const pathname = usePathname();

  return (
    <aside className={`${mobile ? "flex w-full" : "hidden w-72 md:flex"} border-r bg-background h-screen flex-col sticky top-0`}>
      <div className="p-6">
        <Logo />
      </div>

      <nav className="flex-1 space-y-1 overflow-y-auto px-3 pb-5">
        {items.map((item) => {
          const Icon = item.icon;

          const active =
            pathname === item.href ||
            (item.href !== "/admin" && pathname.startsWith(`${item.href}/`));

          return (
            <Link
                key={item.href}
                href={item.href}
                className={cn(
                    "flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-colors",
                    active
                    ? "bg-primary text-primary-foreground"
                    : "hover:bg-muted"
                )}
                >
                <Icon className="h-5 w-5" />
                {item.label}
            </Link>
          );
        })}
      </nav>

      <div className="border-t p-4 text-xs text-muted-foreground">
        Dewdora CMS v1.0
      </div>
    </aside>
  );
}
