# Production readiness

## Installation

1. Set real HTTPS frontend/API origins in both deployments. Add Laravel `APP_KEY`, database, `QUEUE_CONNECTION=database` or Redis, private storage, `OPENAI_API_KEY`, `ELEVENLABS_API_KEY`, and the selected social developer app credentials from `.env.example`. Do not ship `.env` in a ZIP.
2. Install PHP/Composer and Node dependencies, run `php artisan migrate --force`, then `php artisan db:seed --class=LegalPageSeeder --force`. Replace legal placeholders in **Admin → Pages** and check the live public pages.
3. Install FFmpeg and FFprobe with H.264/AAC, make Laravel storage writable, configure a supervised queue worker for generation, sources, revisions and publishing, and a cron invoking `php artisan schedule:run` each minute. See `PRODUCTION-WORKERS.md` for exact commands.
4. In **Affiliate Agent → Setup**, verify provider connection checks, source approval, connected accounts, actual scheduler tick and storage/FFmpeg. A configured queue driver does not prove a worker is running. Set timezone, schedule, mix, slots and scope in **Automation**. Generation and publishing can be enabled independently.
5. Connect each social developer app through OAuth, verify the account and its scopes, and check API review. Configure a real Pinterest board and optionally a section. Test an end-to-end generated video and review every component before **FINAL_APPROVE**. Verify exact version publishing and each platform's processing/visibility status in **Publishing**. API approval and live credentials are external prerequisites.
6. Check public links, footer, sitemap, SEO, affiliate links/disclosure, backups, Laravel logs, failed jobs and queue supervision. Preserve database and private version assets together.

## Verification run for this package

`php artisan test --compact`, `npm run lint`, and `npm run typecheck` exercise the local code. Tests fake external platform APIs; they do not establish live OAuth approval, token scope, publication eligibility, production cron/worker health or provider billing. Use real credentials and authorized test accounts in the target environment.
