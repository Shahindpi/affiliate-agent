import api from "@/lib/axios";

export async function search(query: string) {
  const response = await api.get("/public/search", {
    params: {
      query,
    },
  });

  return response.data.data;
}

export async function suggestions(query: string) {
  const response = await api.get("/public/search/suggestions", {
    params: {
      query,
    },
  });

  return response.data.data;
}