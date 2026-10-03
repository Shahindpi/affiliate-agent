"use client";

import { useRouter } from "next/navigation";

export function RetryHomepage() {
  const router = useRouter();
  return <button type="button" onClick={() => router.refresh()} className="mt-5 rounded-lg bg-[#165e46] px-5 py-3 font-bold text-white">Try again</button>;
}
