# YouTube setup

1. Create a Google Cloud project, enable YouTube Data API v3, configure OAuth consent, and create a Web application OAuth client in [Google Cloud Console](https://console.cloud.google.com/apis/credentials).
2. Set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in Laravel `.env`. Register `https://YOUR-LARAVEL-HOST/api/v1/affiliate-agent/oauth/youtube/callback` as an exact authorized redirect URI.
3. Approve scopes `https://www.googleapis.com/auth/youtube.upload` and `https://www.googleapis.com/auth/youtube.readonly` in the consent setup. Clear Laravel config and restart workers.
4. In **Affiliate Agent → Social accounts → YouTube**, click **Connect**, select the correct Google account/channel, **Test**, and enable publishing.
5. Approve a real version and schedule YouTube. The worker uploads the H.264/AAC master through YouTube's resumable upload protocol and stores returned ID/URL.
6. Check **Publishing** and the YouTube channel. An unverified project may restrict uploaded videos to private; the dashboard marks this `API_REVIEW_REQUIRED` and does not claim a public Short. For public publishing, complete Google's verification/audit and set the account API review status through an administrator-controlled process after confirming approval.

If Google returns `invalid_grant`, reconnect. If `youtubeSignupRequired`, create/select a channel. Check quota errors in Cloud Console. Official [videos.insert](https://developers.google.com/youtube/v3/docs/videos/insert).
