# TikTok setup

1. Create an app in [TikTok for Developers](https://developers.tiktok.com/). Enable Login Kit and Content Posting API; request `user.info.basic`, `video.upload` and `video.publish`. Direct Post needs additional review.
2. Set `TIKTOK_CLIENT_KEY` and `TIKTOK_CLIENT_SECRET` in Laravel `.env`. Register `https://YOUR-LARAVEL-HOST/api/v1/affiliate-agent/oauth/tiktok/callback` in the app's redirect URIs. Clear config and restart workers.
3. Click **Affiliate Agent → Social accounts → TikTok → Connect**, authorize, then **Test** and enable publishing.
4. Approve and schedule a real video. Without verified Direct Post approval, the adapter uploads a draft/inbox video using `video.upload`; the creator must complete it in TikTok. The record says `MANUAL_ACTION_REQUIRED`.
5. With approved Direct Post and `video.publish`, the adapter queries creator info, supplies an available privacy setting and AI disclosure, uploads the file, and polls the publish status. Verify the final post in TikTok before treating it as public.
6. If the app is unaudited, use the draft/manual workflow. Review [Direct Post](https://developers.tiktok.com/docs/en/content-posting-api-reference-direct-post) and [Upload](https://developers.tiktok.com/docs/en/content-posting-api-reference-upload-video) for current limits.
