# Affiliate Agent integration map

This extension uses the existing Next.js root and Laravel 12 / PHP ^8.2 application in `agent-backend`. No application is replaced.

| Concern | Existing implementation reused | Extension |
| --- | --- | --- |
| Authentication | Sanctum bearer tokens, `admin` middleware, AuthProvider/AdminShell | All agent routes use the same admin guard |
| Brands/products/programs | Brand, AffiliateProduct, AffiliateNetwork, their CRUD | Content references existing brands/products; no duplicate catalog |
| Media | Laravel Storage, existing Media model and public image picker | Agent uploads and generated files use separate immutable private paths, authenticated previews; existing image handling stays intact |
| API | `/api/v1/admin`, ApiResponse, Form Requests | `/affiliate-agent` endpoints |
| Frontend | AdminShell, Sidebar, PageTitle, Button/Card/Input, axios, React Query, toast | Native review queue, detail/version comparison and preferences |
| Queues | Existing Laravel jobs table and configured connection | `agent-revisions` and `agent-publishing` jobs with retries |

The targeted deliverable is the content review and revision system. The broader attached brief remains the roadmap for campaigns, research/planning, analytics and production social adapters. This change must not pretend those external services are integrated.

## Invariants

- Content payloads and asset manifests are immutable snapshots. Lifecycle state, feedback, locks, approvals and publications are separate records.
- Approval binds a content ID, version ID and snapshot hash. An accepted revision, direct edit, restore or lock change invalidates current approval and cancels pending publications. Restoring copies an old snapshot into a new version; historical approvals are never revived.
- All mutations require the expected current version. A queue result based on an outdated version cannot overwrite newer work.
- The revision provider proposes structured component changes. Server validation enforces schema, component/scene locks and dependency conflicts independently of AI output.
- Metadata-only feedback reuses the master video and voice. Captions/CTA/disclosure/scenes require rendering. Script/voice changes require voice + rendering. Script changes update captions. QA always reruns.
- Locked downstream output causes a conflict instead of silent regeneration. Admins can explicitly unlock it and resubmit.
- Generated files use UUID paths and checksums. Preview/export requires authenticated, expiring signed URLs. Existing public media is copied to the agent asset store before it becomes part of a version.
- Publishing rechecks the exact current approved version and manifest, inside the same per-content lock used by edits. Duplicate platform/version records are forbidden. Manual export and clearly labeled mock publishing are available; no real API publish is claimed without an adapter.
- Preferences are explicit, scoped, configurable records for future generation only. Feedback never changes existing unrelated content.

## Local setup and review

```sh
# Root: Next.js (use the existing API/storage environment settings)
npm ci
npm run typecheck
npm run build

# Backend: preserve your existing .env and database
cd agent-backend
composer install
php artisan migrate
# Set APP_URL to the real reachable backend origin for signed preview URLs.
# QUEUE_CONNECTION=database for a simple local installation.
php artisan queue:work --queue=agent-revisions,agent-publishing --sleep=1 --tries=3 --timeout=850
php artisan serve --host=127.0.0.1 --port=8000
```

Open **Affiliate Agent → Create content** in the existing admin. Select existing brand/product records, enter the script and scenes, then generate. Every completed master enters review. Missing API keys do not block the mock workflow: mock narration is a labelled test tone. For mock revisions use `set cta: New CTA`, `set scenes.intro.text: New opening` or `set metadata.youtube.title: New title`. Direct component editing also works without AI keys. Configure `AGENT_AI_PROVIDER=openai`, `OPENAI_API_KEY`, `AGENT_VOICE_PROVIDER=elevenlabs`, `ELEVENLABS_API_KEY` and `ELEVENLABS_VOICE_ID` for live generation. Keys stay on Laravel, never in Next.js variables.

Natural-language interpretation is tested with mocked HTTP provider responses. Real provider calls require your credentials and must be tested before production. Price rates are configurable; no cost estimate is invented when rates are absent. Preferences apply to future initial generation with the live provider, not automatically to revisions of existing content. Mock initial generation uses the entered draft verbatim.

Feedback jobs checkpoint the interpreted patch, generated voice and completed render so retries reuse completed assets. Validation failures become visible feedback errors; transient provider/render errors use queue retry/backoff and Laravel failed-job logs. Restore creates a new immutable version and can reuse its verified original assets. Existing approved/publication audit records remain available.

## VPS deployment (Ubuntu 24.04)

Keep the repository root connected to Vercel; deploy only `agent-backend` to the PHP server. The existing PHP ^8.2 requirement and composer lock are unchanged. Install PHP-FPM and required extensions, Nginx, MySQL/MariaDB, Redis, Supervisor and FFmpeg. Configure Nginx's document root to `agent-backend/public`, HTTPS, upload/time limits, and private storage outside the web root. Set a reachable HTTPS `APP_URL`. Trust only the actual reverse proxy when configuring Laravel proxy headers, so signed URL validation uses the correct host/scheme.

Use `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, a Redis `retry_after` above 900 seconds (for example 960), `AGENT_RENDER_TIMEOUT=600`, and PHP upload limits matching the 100 MB asset limit. Redis is also required for distributed render locks. Laravel's default Redis queue retry_after is too short for video rendering: update that setting before starting workers.

Example Supervisor configuration (adjust paths and PHP executable):

```ini
[program:affiliate-agent-render]
command=/usr/bin/php /var/www/dewdora/agent-backend/artisan queue:work redis --queue=agent-revisions --sleep=2 --tries=3 --timeout=850
numprocs=1
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/affiliate-agent-render.log
stopasgroup=true
killasgroup=true
stopwaitsecs=900

[program:affiliate-agent-publish]
command=/usr/bin/php /var/www/dewdora/agent-backend/artisan queue:work redis --queue=agent-publishing --sleep=2 --tries=3 --timeout=180
numprocs=1
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/affiliate-agent-publish.log
stopasgroup=true
killasgroup=true
stopwaitsecs=240
```

Run `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache` and `php artisan queue:restart` on deploy. Run Laravel scheduler every minute using cron. Back up database and private assets together; do not overwrite/delete version assets. Generated media and temporary render files are excluded from Git. Signed previews last 30 minutes and support native video/audio playback; treat those links as temporary bearer capabilities.

Only manual and mock publication modes are implemented in this review extension. Mock publication is `MOCK_PUBLISHED`, without a real external publish time. Manual confirmation requires the actual post URL and records `PUBLISHED_MANUALLY`. Credentials, platform approval and official API adapters are the next publishing integration; they must preserve the exact-version guard and platform-supported idempotency. Research/planning/campaigns and analytics from the broader brief are separate future work.
