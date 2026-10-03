"use client";
export type PageSize = 10 | 20 | 50 | "all";
export function PageSizeSelect({ value, onChange }: { value: PageSize; onChange: (value: PageSize) => void }) {
  return <label className="flex items-center gap-2 text-sm text-foreground">Items per page
    <select aria-label="Items per page" value={value} onChange={event => onChange(event.target.value === "all" ? "all" : Number(event.target.value) as PageSize)} className="rounded-lg border bg-background px-3 py-2 text-foreground">
      {[10, 20, 50].map(size => <option key={size} value={size}>{size}</option>)}<option value="all">All</option>
    </select>
  </label>;
}
