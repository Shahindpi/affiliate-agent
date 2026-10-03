"use client";

import { use } from "react";
import { useQuery } from "@tanstack/react-query";
import { UserForm } from "@/components/admin/users/user-form";
import { getUser } from "@/services/users";
import { apiErrorMessage } from "@/lib/api-error";

export default function EditUserPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["user", id],
    queryFn: () => getUser(Number(id)),
  });
  if (isLoading) return <p>Loading user…</p>;
  if (error) return <p role="alert">{apiErrorMessage(error, "Could not load user.")} <button onClick={() => refetch()} className="underline">Retry</button></p>;
  if (!data) return <p>User not found.</p>;
  return <div><h1 className="mb-6 text-3xl font-bold">Edit user</h1><UserForm user={data} /></div>;
}
