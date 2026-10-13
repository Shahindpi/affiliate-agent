import type { Scene } from "@/types/affiliate-agent";

export function scriptSummaryLabels(scenes: readonly unknown[]): string[] {
  return scenes.map((scene, index) => index === 0 ? "HOOK" : index === scenes.length - 1 ? "FINISHING / CTA" : `CONTENT ${index}`);
}

export function formatScriptSummary(scenes: readonly Pick<Scene, "text">[]): string {
  const labels = scriptSummaryLabels(scenes);
  return scenes.map((scene, index) => `${labels[index]}\n\n${scene.text}`).join("\n\n");
}

export function parseScriptSummary(input: string, scenes: readonly unknown[]): string[] | null {
  const labels = scriptSummaryLabels(scenes);
  const lines = input.replaceAll("\r", "").split("\n");
  const positions = labels.map(label => lines.findIndex(line => line.trim() === label));
  if (positions.some((position, index) => position < 0 || (index > 0 && position <= positions[index - 1])) || lines.slice(0, positions[0]).some(line => line.trim())) return null;
  const texts = positions.map((position, index) => lines.slice(position + 1, positions[index + 1] ?? lines.length).join("\n").trim());
  return texts.every(text => text.length > 0 && text.length <= 500) ? texts : null;
}
