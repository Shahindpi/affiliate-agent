export function apiErrorMessage(error: unknown, fallback = "Request failed.") {
  if (typeof error !== "object" || error === null || !("response" in error)) return fallback;
  const response = (error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response;
  return Object.values(response?.data?.errors || {}).flat().join(" ") || response?.data?.message || fallback;
}
