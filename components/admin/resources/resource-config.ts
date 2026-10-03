import type { ResourceEndpoint } from "@/services/admin-resources";

export type ResourceField = {
  key: string;
  label: string;
  kind?: "text" | "textarea" | "number" | "url" | "checkbox" | "select" | "array";
  required?: boolean;
  options?: { label: string; value: string }[];
};

export type ResourceConfig = {
  title: string;
  endpoint: ResourceEndpoint;
  fields: ResourceField[];
};

const name: ResourceField = { key: "name", label: "Name", required: true };
const slug: ResourceField = { key: "slug", label: "Slug" };

export const resourceConfigs = {
  categories: {
    title: "Categories",
    endpoint: "categories",
    fields: [
      name,
      slug,
      { key: "description", label: "Description", kind: "textarea" },
      { key: "image", label: "Image path" },
      { key: "sort_order", label: "Sort order", kind: "number" },
      { key: "status", label: "Active", kind: "checkbox" },
    ],
  },
  tags: { title: "Tags", endpoint: "tags", fields: [name, slug] },
  brands: {
    title: "Brands",
    endpoint: "brands",
    fields: [
      name,
      slug,
      { key: "description", label: "Description", kind: "textarea" },
      { key: "website", label: "Website", kind: "url" },
      { key: "logo", label: "Logo path" },
      { key: "status", label: "Active", kind: "checkbox" },
    ],
  },
  networks: {
    title: "Affiliate networks",
    endpoint: "affiliate-networks",
    fields: [
      name,
      slug,
      { key: "description", label: "Description", kind: "textarea" },
      { key: "website", label: "Website", kind: "url" },
      { key: "status", label: "Active", kind: "checkbox" },
    ],
  },
  roles: {
    title: "Roles",
    endpoint: "roles",
    fields: [
      name,
      { ...slug, required: true },
      { key: "description", label: "Description", kind: "textarea" },
      { key: "status", label: "Active", kind: "checkbox" },
    ],
  },
} satisfies Record<string, ResourceConfig>;

export type ResourceKey = keyof typeof resourceConfigs;

export function isResourceKey(value: string): value is ResourceKey {
  return Object.hasOwn(resourceConfigs, value);
}
