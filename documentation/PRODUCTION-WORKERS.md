# Production workers

1. Install Composer dependencies under PHP 8.2+, run migrations (`php artisan migrate --force`), install FFmpeg/FFprobe with H.264 and AAC support, and set `APP_URL` to the real HTTPS API host.
2. Set `QUEUE_CONNECTION=database` or Redis, configure queue retry-after above the 850-second render timeout, and supervise independent workers: `php artisan queue:work --queue=agent-generation,agent-sources --tries=2`, `php artisan queue:work --queue=agent-revisions --timeout=850 --tries=3`, `php artisan queue:work --queue=agent-publishing --tries=3`. Restart them after environment/config changes.
3. Configure cron to run `php artisan schedule:run` once per minute. The registered `agent:tick` command queues source syncs, daily generation and due platform publications. You may test it manually with `php artisan agent:tick`.
4. Use **Automation → Test FFmpeg / Queue / Scheduler** for configuration checks. A Queue result checks driver configuration, not whether an OS worker process is alive; verify it with supervisor and queued jobs. Scheduler check likewise requires observing cron execution.
5. Monitor `php artisan queue:failed`, Laravel logs, source sync runs and Publishing dashboard. Preserve the private `storage/app/affiliate-agent/assets` files and database together for immutable version history.
