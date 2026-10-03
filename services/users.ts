import api from "@/lib/axios";
import type { ApiResponse, PaginationMeta } from "@/types/api";
import type { AdminRole, User } from "@/types/user";
import { allResourceOptions } from "@/services/admin-resources";

export interface UserPayload {
  role_id: number;
  name: string;
  username: string;
  email: string;
  phone: string | null;
  status: boolean;
  password?: string;
  password_confirmation?: string;
}

export async function getUsers(params?: Record<string, string | number | boolean | undefined>) {
  const response = await api.get<ApiResponse<User[]>>("/admin/users", { params });
  return { users: response.data.data, meta: response.data.meta as PaginationMeta };
}

export async function getUser(id: number) {
  const response = await api.get<ApiResponse<User>>(`/admin/users/${id}`);
  return response.data.data;
}

export async function getRoles() {
  return allResourceOptions<AdminRole>("roles");
}

export async function createUser(payload: UserPayload) {
  const response = await api.post<ApiResponse<User>>("/admin/users", payload);
  return response.data.data;
}

export async function updateUser(id: number, payload: Omit<UserPayload, "password" | "password_confirmation">) {
  const response = await api.put<ApiResponse<User>>(`/admin/users/${id}`, payload);
  return response.data.data;
}

export async function resetUserPassword(id: number, password: string, passwordConfirmation: string) {
  await api.put(`/admin/users/${id}/password`, { password, password_confirmation: passwordConfirmation });
}

export async function deleteUser(id: number) {
  await api.delete(`/admin/users/${id}`);
}
