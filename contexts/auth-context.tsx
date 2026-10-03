"use client";

import { createContext } from "react";

import { User } from "@/types/user";

export interface AuthContextType {
  user: User | null;

  loading: boolean;

  authenticated: boolean;

  login: (email: string, password: string) => Promise<void>;

  logout: () => Promise<void>;

  refreshUser: () => Promise<void>;
}

export const AuthContext =
  createContext<AuthContextType | null>(null);