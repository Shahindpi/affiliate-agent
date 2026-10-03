"use client";

import { Toaster } from "sonner";
import { AuthProvider } from "@/providers/auth-provider";
import { QueryProvider } from "@/providers/query-provider";
import { ReduxProvider } from "@/providers/redux-provider";
import { ThemeProvider } from "@/providers/theme-provider";

export function AdminProviders({ children }: { children: React.ReactNode }) {
  return <ThemeProvider><ReduxProvider><QueryProvider><AuthProvider>{children}<Toaster richColors position="top-right" /></AuthProvider></QueryProvider></ReduxProvider></ThemeProvider>;
}
