"use client";

import { Toaster } from "sonner";
import { AuthProvider } from "@/providers/auth-provider";
import { QueryProvider } from "@/providers/query-provider";

export function AuthProviders({ children }: { children: React.ReactNode }) {
  return <QueryProvider><AuthProvider>{children}<Toaster richColors position="top-right" /></AuthProvider></QueryProvider>;
}
