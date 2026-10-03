import type { Route } from "next";

// Static paths are checked against Next's generated App Router route types.
export const routes = {
  home: "/" satisfies Route,
  login: "/auth/login" satisfies Route,
  contact: "/contact" satisfies Route,
  search: "/search" satisfies Route,
  products: { index: "/products" satisfies Route, show: (slug: string) => `/products/${encodeURIComponent(slug)}` as Route },
  posts: { index: "/posts" satisfies Route, show: (slug: string) => `/posts/${encodeURIComponent(slug)}` as Route },
  categories: { index: "/categories" satisfies Route, show: (slug: string) => `/categories/${encodeURIComponent(slug)}` as Route },
  brands: { index: "/brands" satisfies Route, show: (slug: string) => `/brands/${encodeURIComponent(slug)}` as Route },
  tags: { index: "/tags" satisfies Route, show: (slug: string) => `/tags/${encodeURIComponent(slug)}` as Route },
  admin: {
    dashboard: "/admin" satisfies Route, analytics: "/admin/analytics" satisfies Route,
    posts: { index: "/admin/posts" satisfies Route, create: "/admin/posts/new" satisfies Route, edit: (id: number | string) => `/admin/posts/${id}` as Route },
    products: { index: "/admin/products" satisfies Route, create: "/admin/products/new" satisfies Route, edit: (id: number | string) => `/admin/products/${id}` as Route },
    users: { index: "/admin/users" satisfies Route, create: "/admin/users/new" satisfies Route, edit: (id: number | string) => `/admin/users/${id}` as Route },
    categories: "/admin/categories" satisfies Route, tags: "/admin/tags" satisfies Route,
    brands: "/admin/brands" satisfies Route, networks: "/admin/networks" satisfies Route,
    media: "/admin/media" satisfies Route, roles: "/admin/roles" satisfies Route,
    settings: "/admin/settings" satisfies Route, profile: "/admin/profile" satisfies Route,
    comments: "/admin/comments" satisfies Route, contacts: "/admin/contacts" satisfies Route,
    contact: "/admin/contact" satisfies Route, newsletter: "/admin/newsletter" satisfies Route,
    subscribers: "/admin/subscribers" satisfies Route,
    heroBanners: "/admin/hero-banners" satisfies Route,
    homepageSettings: "/admin/homepage-settings" satisfies Route,
  },
} as const;

export const publicNavigation = [
  { label: "Products", href: routes.products.index },
  { label: "Categories", href: routes.categories.index },
  { label: "Reviews & Guides", href: routes.posts.index },
  { label: "Brands", href: routes.brands.index },
  { label: "Search", href: routes.search },
  { label: "Contact", href: routes.contact },
] as const;

// Homepage banners are editable; link only to known public paths.
export function publicBannerRoute(value: string): Route | null {
  try {
    if (!value.startsWith("/") || value.startsWith("//")) return null;
    const url = new URL(value, "https://dewdora.invalid");
    if (url.origin !== "https://dewdora.invalid") return null;
    const path = url.pathname;
    const staticPaths = publicNavigation.map(item => item.href as string).concat(routes.home, routes.tags.index);
    if (staticPaths.includes(path) || /^\/(products|posts|categories|brands|tags)\/[^/]+$/.test(path)) return value as Route;
  } catch { /* Invalid banner URL. */ }
  return null;
}
