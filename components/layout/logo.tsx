"use client";
import Image from "next/image";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { getSettings } from "@/services/settings";
export default function Logo() {
  const [imageFailed, setImageFailed] = useState(false);
  const { data: settings } = useQuery({ queryKey: ["public-settings"], queryFn: getSettings, staleTime: 30 * 60 * 1000 });
  return <Image src={settings?.logo && !imageFailed ? settings.logo : "/dewdora-logo.svg"} alt="Dewdora" width={186} height={48} className="h-12 w-auto object-contain" unoptimized onError={() => setImageFailed(true)} />;
}
