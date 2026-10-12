export type AgentMediaLimits = { image: number; video: number };

export function validateAgentMediaFile(file: File, limits: AgentMediaLimits, kind?: "image" | "video"): string | null {
  const supported: Record<string, string[]> = {
    "image/jpeg": ["jpg", "jpeg"], "image/png": ["png"], "image/webp": ["webp"], "video/mp4": ["mp4"],
  };
  const extension = file.name.split(".").pop()?.toLowerCase();
  const actual = file.type === "video/mp4" ? "video" : "image";
  if (!supported[file.type]?.includes(extension || "") || (kind && actual !== kind)) return "Choose a JPEG, PNG, or WebP image, or an MP4 video matching the selected source.";
  if (file.size > limits[actual] * 1024 * 1024) return `${actual === "video" ? "Videos" : "Images"} must be ${limits[actual]} MB or smaller.`;
  return null;
}
