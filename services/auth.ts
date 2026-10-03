import api from "@/lib/axios";

import { ApiResponse } from "@/types/api";
import { User } from "@/types/user";

export interface LoginPayload {
  email: string;
  password: string;
}

export interface LoginResponse {
  token: string;
  user: User;
}

export async function login(payload: LoginPayload): Promise<LoginResponse> {
  const response = await api.post<ApiResponse<LoginResponse>>(
    "/auth/login",
    payload
  );

  return response.data.data;
}

export async function me() {
  const response = await api.get<ApiResponse<User>>("/auth/me");

  return response.data.data;
}

export async function logout() {
  await api.post("/auth/logout");
}