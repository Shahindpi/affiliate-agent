# Dewdora frontend

Next.js 16 frontend for the Dewdora Laravel API in `../backend`. The public site provides articles, products, categories, tags, brands, search, comments, contact and newsletter signup. The authenticated admin provides dashboard, posts, categories, tags, affiliate products, brands, networks, media, comment/contact moderation, subscribers, profile and site settings.

Copy `.env.example` to `.env.local` and set `NEXT_PUBLIC_API_URL` to the **versioned API root** (`/api/v1`), not `/api` or `/api/v1/public`. Set `NEXT_PUBLIC_STORAGE_URL` to the Laravel `/storage` URL. Laravel must allow the frontend origin in `CORS_ALLOWED_ORIGINS` and expose uploaded files with `php artisan storage:link`.

Run `npm ci`, `npm run dev`, `npx tsc --noEmit`, `npm run lint`, and `npm run build`. The public pages request current content from Laravel at render time; publish the Laravel API at an address reachable from the Next.js server. If necessary, configure server-only `API_URL` separately. Admin authentication uses a Sanctum bearer token stored in the browser.

## Troubleshooting a homepage API 500

The homepage fetches `${API_URL || NEXT_PUBLIC_API_URL}/public/homepage` from Laravel on the server. A 500 is an upstream Laravel failure, not a Next.js typed-route or rendering error. The frontend displays a retry state; it cannot repair a failed database query. Request `/api/v1/public/homepage` directly, inspect `backend/storage/logs/laravel.log`, and run `php artisan migrate:status` in the deployed `backend/` directory. This branch adds the `affiliate_events.is_demo` column in migration `2026_09_20_000000_mark_demo_affiliate_events.php`, which the homepage popularity query requires. Apply pending migrations with `php artisan migrate --force` (do not use `migrate:fresh` on real data), then run `php artisan optimize:clear` and retry the endpoint. Also verify `API_URL`/`NEXT_PUBLIC_API_URL` points to the correct deployed Laravel instance and database. A current Laravel exception log is required to identify other 500 causes.
