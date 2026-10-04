# Troubleshooting

- **403 admin endpoint:** log in to Dewdora with an active admin role and valid Sanctum bearer token.
- **OAuth callback invalid state:** restart Connect, finish within ten minutes, verify shared cache between web nodes, and match redirect URI exactly. Callback state is one-use.
- **Provider key configured but no generation:** run `php artisan config:clear`, restart workers, inspect `queue:failed`; upload product media and approve source documents.
- **Source sync failed:** check HTTPS host allowlist, public DNS, robots.txt, redirect response, official URL and worker logs. Redirects are blocked deliberately.
- **Missing voice or FFmpeg failure:** inspect `/admin/affiliate-agent` usage and failed jobs; verify the selected voice, FFmpeg binary, fonts and script duration.
- **Connected but publish unavailable:** enable the account and production setting, inspect scopes, creator/account type and app review. Mock content is blocked from real APIs.
- **TikTok draft:** complete posting in the TikTok app; a draft is not a public post.
- **YouTube private:** verify API project audit status and channel; unverified projects may be private-only.
- **Meta cannot fetch video:** use public HTTPS `APP_URL` and allow the signed asset route to serve MP4 externally during the upload window.
- **Manual action required after an error:** inspect the platform before retrying to avoid duplicate posts. Download video and copy captions as fallback.
- **No daily items:** verify generation days/timezone, active brand/product, source approval, media, `agent:tick` cron, and queue workers.
- **Pinterest migration failed with MySQL identifier 1059:** use the updated migration and rerun `php artisan migrate`. MySQL may have already created `agent_pinterest_destinations`; the updated migration adds the short `agent_pin_dest_scope_uq` index to that table and finishes. Do not drop the table if it contains data.
