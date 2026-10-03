import { User } from "@/types/user";

const TOKEN_KEY = "dewdora_token";
const USER_KEY = "dewdora_user";

export function getToken(): string | null {
  if (typeof window === "undefined") return null;

  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string | null) {
  if (!token) {
    localStorage.removeItem(TOKEN_KEY);
    return;
  }

  localStorage.setItem(TOKEN_KEY, token);
}

export function removeToken() {
  localStorage.removeItem(TOKEN_KEY);
}

export function getStoredUser(): User | null {
  if (typeof window === "undefined") return null;

  const value = localStorage.getItem(USER_KEY);

  // Nothing stored.
  if (!value || value === "undefined" || value === "null") {
    return null;
  }

  try {
    return JSON.parse(value) as User;
  } catch (error) {
    console.warn("Invalid stored user. Clearing storage.", error);

    localStorage.removeItem(USER_KEY);

    return null;
  }
}

export function setStoredUser(user: User | null) {
  if (!user) {
    localStorage.removeItem(USER_KEY);
    return;
  }

  localStorage.setItem(USER_KEY, JSON.stringify(user));
}

export function removeStoredUser() {
  localStorage.removeItem(USER_KEY);
}

export function clearAuthStorage() {
  removeToken();
  removeStoredUser();
}