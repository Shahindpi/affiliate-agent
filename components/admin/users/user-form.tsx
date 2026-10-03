"use client";
import { routes } from "@/lib/routes";

import { useEffect, useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiErrorMessage } from "@/lib/api-error";
import { createUser, getRoles, resetUserPassword, updateUser } from "@/services/users";
import type { AdminRole, User } from "@/types/user";

export function UserForm({ user }: { user?: User }) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const [roles, setRoles] = useState<AdminRole[]>([]);
  const [busy, setBusy] = useState(false);
  const [passwordBusy, setPasswordBusy] = useState(false);
  const [roleId, setRoleId] = useState(String(user?.role?.id || ""));

  useEffect(() => {
    getRoles().then(setRoles).catch((error) => toast.error(apiErrorMessage(error, "Could not load roles.")));
  }, []);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    const data = new FormData(event.currentTarget);
    const payload = {
      role_id: Number(data.get("role_id")),
      name: String(data.get("name") || ""),
      username: String(data.get("username") || ""),
      email: String(data.get("email") || ""),
      phone: String(data.get("phone") || "") || null,
      status: data.has("status"),
    };

    try {
      if (user) {
        await updateUser(user.id, payload);
      } else {
        await createUser({
          ...payload,
          password: String(data.get("password") || ""),
          password_confirmation: String(data.get("password_confirmation") || ""),
        });
      }
      await queryClient.invalidateQueries({ queryKey: ["users"] });
      await queryClient.invalidateQueries({ queryKey: ["user"] });
      toast.success(user ? "User updated." : "User created.");
      router.push(routes.admin.users.index);
      router.refresh();
    } catch (error) {
      toast.error(apiErrorMessage(error, "Could not save user."));
    } finally {
      setBusy(false);
    }
  }

  async function changePassword(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!user) return;
    const form = event.currentTarget;
    const data = new FormData(form);
    setPasswordBusy(true);
    try {
      await resetUserPassword(user.id, String(data.get("password")), String(data.get("password_confirmation")));
      toast.success("Password changed and active sessions revoked.");
      form.reset();
    } catch (error) {
      toast.error(apiErrorMessage(error, "Could not change password."));
    } finally {
      setPasswordBusy(false);
    }
  }

  const input = "mt-2 w-full rounded-lg border bg-background p-3";
  return <div className="max-w-3xl space-y-6">
    <form onSubmit={submit} className="grid gap-5 rounded-2xl border bg-background p-6 md:grid-cols-2">
      <label className="text-sm font-medium">Name<input className={input} name="name" required defaultValue={user?.name} /></label>
      <label className="text-sm font-medium">Username<input className={input} name="username" required defaultValue={user?.username} /></label>
      <label className="text-sm font-medium">Email<input className={input} name="email" type="email" required defaultValue={user?.email} /></label>
      <label className="text-sm font-medium">Phone<input className={input} name="phone" defaultValue={user?.phone || ""} /></label>
      <label className="text-sm font-medium">Role<select className={input} name="role_id" required value={roleId} onChange={event => setRoleId(event.target.value)}><option value="">Select a role</option>{roles.filter(role => role.status).map(role => <option key={role.id} value={role.id}>{role.name}</option>)}</select></label>
      <label className="flex items-center gap-3 self-end rounded-lg border p-3 text-sm font-medium"><input type="checkbox" name="status" defaultChecked={user?.status ?? true} /> Active user</label>
      {!user && <><label className="text-sm font-medium">Password<input className={input} name="password" type="password" minLength={8} required /></label><label className="text-sm font-medium">Confirm password<input className={input} name="password_confirmation" type="password" minLength={8} required /></label></>}
      <div className="flex gap-3 md:col-span-2"><button disabled={busy} className="rounded-lg bg-primary px-5 py-3 text-primary-foreground disabled:opacity-50">{busy ? "Saving…" : "Save user"}</button><button type="button" onClick={() => router.push(routes.admin.users.index)} className="rounded-lg border px-5 py-3">Cancel</button></div>
    </form>
    {user && <form onSubmit={changePassword} className="grid gap-5 rounded-2xl border bg-background p-6 md:grid-cols-2"><div className="md:col-span-2"><h2 className="text-xl font-semibold">Reset password</h2><p className="text-sm text-muted-foreground">Changing the password revokes this user&apos;s active API sessions.</p></div><label className="text-sm font-medium">New password<input className={input} name="password" type="password" minLength={8} required /></label><label className="text-sm font-medium">Confirm password<input className={input} name="password_confirmation" type="password" minLength={8} required /></label><button disabled={passwordBusy} className="w-fit rounded-lg border px-5 py-3">{passwordBusy ? "Changing…" : "Change password"}</button></form>}
  </div>;
}
