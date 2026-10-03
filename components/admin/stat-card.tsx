"use client";

import { LucideIcon } from "lucide-react";
import Link from "next/link";
import type { Route } from "next";

interface Props {
  title: string;
  value: number | string;
  icon: LucideIcon;
  color: string;
  href?: Route;
}

export default function StatCard({
  title,
  value,
  icon: Icon,
  color,
  href,
}: Props) {
  const card = (
    <div className="h-full rounded-2xl border bg-background p-6 shadow-sm hover:shadow-md transition-shadow">
      <div className="flex items-center justify-between mb-5">
        <div
          className="h-12 w-12 rounded-xl flex items-center justify-center"
          style={{ backgroundColor: `${color}20` }}
        >
          <Icon
            className="h-6 w-6"
            style={{ color }}
          />
        </div>
      </div>

      <p className="text-sm text-muted-foreground">{title}</p>

      <h2 className="mt-2 text-3xl font-bold">{value}</h2>
    </div>
  );
  return href ? <Link href={href}>{card}</Link> : card;
}
