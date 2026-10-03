"use client";
import { routes } from "@/lib/routes";

import { ExternalLink, Menu, Sun, Moon, Monitor, LogOut } from "lucide-react";
import Link from "next/link";
import {
  Avatar,
  AvatarFallback,
  AvatarImage,
} from "@/components/ui/avatar";

import { useTheme } from "@/providers/theme-provider";
import { useAuth } from "@/hooks/use-auth";
import Sidebar from "@/components/admin/sidebar";
import { Sheet, SheetContent, SheetTrigger, SheetTitle } from "@/components/ui/sheet";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export default function AdminHeader() {
  const { user, logout } = useAuth();
  const { theme, setTheme } = useTheme();

  return (
    <header className="sticky top-0 z-40 h-16 border-b bg-background/95 backdrop-blur px-6 flex items-center justify-between">
      <Sheet>
        <SheetTrigger className="mr-3 rounded-lg border p-2 md:hidden" aria-label="Open admin navigation">
          <Menu className="h-5 w-5" />
        </SheetTrigger>
        <SheetContent side="left" className="w-80 p-0">
          <SheetTitle className="sr-only">Admin navigation</SheetTitle>
          <Sidebar mobile />
        </SheetContent>
      </Sheet>

      <div className="ml-auto flex items-center gap-3 sm:gap-5"><label className="flex items-center gap-1 text-sm"><span className="sr-only">Dashboard theme</span>{theme === "dark" ? <Moon className="h-4 w-4" /> : theme === "light" ? <Sun className="h-4 w-4" /> : <Monitor className="h-4 w-4" />}<select aria-label="Dashboard theme" value={theme} onChange={event => setTheme(event.target.value as "light" | "dark" | "system")} className="rounded-lg border bg-background px-2 py-1 text-foreground"><option value="system">System</option><option value="light">Light</option><option value="dark">Dark</option></select></label>
        <Link href={routes.home} target="_blank" className={cn(buttonVariants({ variant: "outline", size: "sm" }), "hidden sm:inline-flex")}>
          View site
          <ExternalLink className="ml-2 h-4 w-4" />
        </Link>

        <details className="relative">
          <summary className="flex cursor-pointer list-none items-center gap-3 rounded-xl px-2 py-1 hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring" aria-label="Account menu">
          <Avatar>
            <AvatarImage src={user?.avatar ?? ""} />

            <AvatarFallback>
              {user?.name?.charAt(0)}
            </AvatarFallback>
          </Avatar>

          <div className="hidden sm:block">
            <p className="text-sm font-medium">
              {user?.name}
            </p>

            <p className="text-xs text-muted-foreground">
              {user?.email}
            </p>
          </div>
          </summary>
          <div className="absolute right-0 mt-2 w-56 rounded-xl border bg-popover p-2 text-popover-foreground shadow-xl"><p className="truncate px-3 py-2 text-xs text-muted-foreground sm:hidden">{user?.email}</p><Link href={routes.admin.profile} className="block rounded-lg px-3 py-2 text-sm hover:bg-muted">Profile / Account</Link><button type="button" onClick={() => void logout()} className="flex w-full cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-muted"><LogOut className="size-4" /> Logout</button></div>
        </details>
      </div>
    </header>
  );
}
