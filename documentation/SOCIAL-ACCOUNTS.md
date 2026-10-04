# Social accounts

1. Deploy Laravel with an HTTPS `APP_URL` reachable by the platform and set `AGENT_ADMIN_URL` to the admin Social accounts page on your Next.js site.
2. Configure platform app credentials in `agent-backend/.env` using the platform guides below, then run `php artisan config:clear` (and restart workers). Register callback URI exactly as shown in each guide.
3. Open **Affiliate Agent → Social accounts**, click **Connect**, grant requested scopes, and return to the dashboard. For Meta, the flow discovers authorized Facebook Pages and linked Instagram business accounts; select publishing per account by enabling the desired record.
4. Click **Test** and inspect connection, expiry and API review status. Enable publishing on the selected account only after a real authorized test.
5. Use **Disconnect** to remove locally stored tokens and stop future sends. Previously published post IDs remain in the audit history.

OAuth tokens are Laravel encrypted at rest and omitted from API responses. The provider controls app approval, creator eligibility, quotas and visibility. `API_REVIEW_REQUIRED`, `PROCESSING`, and `MANUAL_ACTION_REQUIRED` are not publication success. Every approved version can still be downloaded with platform captions for a manual post.
