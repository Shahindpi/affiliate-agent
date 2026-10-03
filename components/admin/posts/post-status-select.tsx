"use client";

interface Props {
  value: "draft" | "published";
  onChange: (value: "draft" | "published") => void;
}

export default function PostStatusSelect({
  value,
  onChange,
}: Props) {
  return (
    <div className="space-y-2">
      <label className="text-sm font-medium">
        Status
      </label>

      <select
        value={value}
        onChange={(e) =>
          onChange(
            e.target.value as "draft" | "published"
          )
        }
        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
      >
        <option value="draft">Draft</option>

        <option value="published">
          Published
        </option>
      </select>
    </div>
  );
}