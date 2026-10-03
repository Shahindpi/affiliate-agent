import axios from "axios";
import { getToken, clearAuthStorage } from "@/lib/storage";

const api = axios.create({
  baseURL: (process.env.NEXT_PUBLIC_API_URL || "http://127.0.0.1:8000/api/v1").replace(/\/$/, ""),
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
  },
});

/*
|--------------------------------------------------------------------------
| Request Interceptor
|--------------------------------------------------------------------------
*/

api.interceptors.request.use(
  (config) => {
    const token = getToken();

    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
  },
  (error) => Promise.reject(error)
);

/*
|--------------------------------------------------------------------------
| Response Interceptor
|--------------------------------------------------------------------------
*/

api.interceptors.response.use(
  (response) => response,

  (error) => {
    if (
      typeof window !== "undefined" &&
      error.response?.status === 401
    ) {
      clearAuthStorage();
      window.dispatchEvent(new Event("dewdora:unauthorized"));
    }

    return Promise.reject(error);
  }
);

export default api;
