"use client";

import { Input } from "@/components/ui/input";

interface Props {
  value: string;
  onChange: (value: string) => void;
}

export default function PostSlugInput({
  value,
  onChange,
}: Props) {
  return (
    <div className="space-y-2">
      <label className="text-sm font-medium">
        Slug
      </label>

      <Input
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder="post-url-slug"
      />

      <p className="text-xs text-muted-foreground">
        dewdora.com/{value || "your-post-slug"}
      </p>
    </div>
  );
}