"use client";
import { routes } from "@/lib/routes";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { useQueryClient } from "@tanstack/react-query";

import { AuthContext } from "@/contexts/auth-context";

import {
  clearAuthStorage,
  getToken,
  setStoredUser,
  setToken,
} from "@/lib/storage";

import {
  login as loginRequest,
  logout as logoutRequest,
  me,
} from "@/services/auth";

import { User } from "@/types/user";

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const router = useRouter();
  const queryClient = useQueryClient();

  const authenticated = !!user;

  const refreshUser = useCallback(async () => {
    setLoading(true);

    const token = getToken();

    if (!token) {
      setUser(null);
      setLoading(false);
      return;
    }

    try {
      const currentUser = await me();
      setUser(currentUser);
      setStoredUser(currentUser);
    } catch {
      clearAuthStorage();
      queryClient.clear();
      setUser(null);
    } finally {
      setLoading(false);
    }
  }, [queryClient]);

  const login = useCallback(async (email: string, password: string) => {
    const response = await loginRequest({ email, password });

    setToken(response.token);
    setStoredUser(response.user);
    setUser(response.user);
  }, []);

  const logout = useCallback(async () => {
    try {
      await logoutRequest();
    } catch {
      // A failed network request must not leave a local session active.
    } finally {
      clearAuthStorage();
      queryClient.clear();
      setUser(null);

      router.replace(routes.login);
    }
  }, [router, queryClient]);

  useEffect(() => {
    const unauthorized = () => {
      queryClient.clear();
      setUser(null);
      router.replace(routes.login);
    };
    window.addEventListener("dewdora:unauthorized", unauthorized);
    return () =>
      window.removeEventListener("dewdora:unauthorized", unauthorized);
  }, [router, queryClient]);

  useEffect(() => {
    void Promise.resolve().then(refreshUser);
  }, [refreshUser]);

  const value = useMemo(
    () => ({
      user,

      loading,

      authenticated,

      login,

      logout,

      refreshUser,
    }),
    [user, loading, authenticated, login, logout, refreshUser],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
