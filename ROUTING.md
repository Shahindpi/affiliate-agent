# Frontend routing and script audit

The source of truth is `app/` and the generated Next route types. `next.config.ts` enables `typedRoutes`; `lib/routes.ts` defines checked static paths, route builders for dynamic pages and the shared public navigation. These are page URLs, not Laravel `/api/v1` paths.

| Public | Admin |
| --- | --- |
| `/`, `/products`, `/products/[slug]` | `/admin`, `/admin/analytics` |
| `/posts`, `/posts/[slug]` | `/admin/posts`, `/admin/posts/new`, `/admin/posts/[id]` |
| `/categories`, `/categories/[slug]` | `/admin/products`, `/admin/products/new`, `/admin/products/[id]` |
| `/brands`, `/brands/[slug]` | `/admin/users`, `/admin/users/new`, `/admin/users/[id]` |
| `/tags`, `/tags/[slug]` | `/admin/categories`, `/admin/tags`, `/admin/brands`, `/admin/networks` |
| `/search`, `/contact`, `/auth/login` | `/admin/media`, `/admin/roles`, `/admin/settings`, `/admin/profile` |
| | `/admin/hero-banners`, `/admin/homepage-settings`, `/admin/newsletter`, `/admin/subscribers`, `/admin/comments`, `/admin/contact`, `/admin/contacts` |

The older generic list type advertised `/affiliate-networks/[slug]`, which does not exist; it has been removed. The editable hero banner CTA now links internally only to known public routes and accepts external HTTP(S) links. Admin sidebar paths resolve to `/admin/...`; the frontend login is `/auth/login`. No route-related `as any` casts were present or added. The stale `.next` output was cleared before generating route types and building.

The installed `next-themes` provider rendered an inline `<script>` on every mount. A subsequent inline `next/script` theme initializer also triggered a React console error in Next.js 16.3 development rendering. The theme provider now applies the persisted light/dark/system preference after hydration through React effects and does not render any theme script; a brief initial system/default-theme flash is possible before hydration. GA4 continues to load through global `next/script` and the validated `NEXT_PUBLIC_GA_ID`; no database-provided script is executed. `JsonLd` emits escaped structured-data markup on server-rendered public pages. Backend site settings store only a validated GA Measurement ID, which does not inject a script. No manual CSS preload tags were found; the editor stylesheet is imported only by its editor component, so occasional framework-generated CSS preload timing notices are separate from the script warning.
