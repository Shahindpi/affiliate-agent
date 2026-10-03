"use client";

import { useAuth } from "@/hooks/use-auth";

export default function AuthStatus() {
  const { user, authenticated, loading } = useAuth();

  if (loading) {
    return <p>Checking session...</p>;
  }

  if (!authenticated) {
    return <p>Not Logged In</p>;
  }

  return (
    <div className="space-y-1 rounded-xl border p-4">
      <h3 className="font-semibold">
        {user?.name}
      </h3>

      <p>{user?.email}</p>

      <p>{user?.role?.name}</p>
    </div>
  );
}