# Editable legal pages

The site uses the existing Laravel `pages` table and admin role. Run migrations, then run `php artisan db:seed --class=LegalPageSeeder --force` to create Privacy Policy, Terms & Conditions and Affiliate Disclosure once. Re-running the seeder preserves edits and creates only missing SEO records. In local development `DatabaseSeeder` includes these templates.

Open **Admin → Pages** to edit rich text, excerpt, SEO title/description, slug and published status. The editor saves sanitized HTML; the public API sanitizes legacy HTML again. The three legal pages have stable `legal_key` values. Changing a legal page slug redirects its original `/privacy-policy`, `/terms` or `/affiliate-disclosure` path to the current slug. Unpublished pages return 404, leave the footer and sitemap, and are omitted from search indexing. Published edits are visible on the next request. The public page has title, description, canonical URL and update date.

The templates deliberately contain `[EDIT: …]` placeholders for the operator, contact details and jurisdiction. Replace these and review actual analytics, cookie, retention, affiliate program and provider practices with your legal adviser before publishing the site. Individual affiliate content still needs its per-post disclosure, which is checked in the review workflow.

Admin API: `GET/POST /api/v1/admin/pages`, `GET/PUT /api/v1/admin/pages/{id}`. Public API: `GET /api/v1/public/pages`, `GET /api/v1/public/pages/{slug}`. The public list contains published legal pages for the footer and sitemap.
