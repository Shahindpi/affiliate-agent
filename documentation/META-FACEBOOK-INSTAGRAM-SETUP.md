# Facebook and Instagram via Meta

1. Create a Meta developer app at [Meta for Developers](https://developers.facebook.com/). Add Facebook Login; request `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`, `instagram_basic`, `instagram_content_publish` as applicable to your Page and linked professional Instagram account.
2. Set `META_APP_ID`, `META_APP_SECRET`, and an officially supported `META_GRAPH_VERSION` in Laravel `.env`. Register `https://YOUR-LARAVEL-HOST/api/v1/affiliate-agent/oauth/meta/callback` as the exact valid OAuth redirect URI. Clear config and restart workers.
3. Connect from **Affiliate Agent → Social accounts → Facebook or Instagram**. One Meta consent flow discovers Pages and eligible linked Instagram accounts. Enable the intended Page and Instagram records individually, then click **Test**.
4. Set public HTTPS `APP_URL` so Meta can fetch the signed, time-limited master video URL. Approve a real version and schedule each destination separately.
5. Facebook uses the Page Reel upload phases; Instagram creates a Reel container, checks its processing state, then publishes. Verify returned IDs/URLs and public visibility in Meta's own tools. If Page permission, professional account eligibility, app review, or media access fails, use manual export and inspect the error in **Publishing**.

Follow the latest Meta content publishing and video API documentation for your selected Graph version; product permissions and app review can change. Tokens are only kept server-side.
