# Short-form video design

## Scene plan

The Create Content form separates narrated script from on-screen scene descriptions, headline, supporting body, media, animation, layout and reusable overlay. The default plan is hook → demo/body → CTA/closing; automatic drafts can choose 3–5 scenes with a problem or benefit between those points. Scenes retain their existing `id`, `text`, `duration` and `media_id`; optional `purpose`, `headline`, `body`, `visual_style`, `background_type`, `animation`, `layout` and `template` are saved with each immutable version. Existing versions without those fields still render.

The automatic planner varies style, layout and motion deterministically across scenes. It prefers uploaded media whose name matches the product, then rotates through available Affiliate Agent images/video. Demo scenes prefer product media where available. To get realistic nature, technology, lifestyle or UI backgrounds, upload approved/licensed footage or product screenshots and choose them per scene. The renderer does not pretend a procedural gradient is realistic footage. With no media, it creates an animated abstract gradient. Solid white and dark are explicit choices, never the automatic fallback.

## Overlays and rendering

FFmpeg crops media to fill a 1080×1920 canvas with gentle movement and contrast shading. Headline, body, caption, CTA and disclosure have separate ASS subtitle styles. Headline is the largest; supporting body is smaller; CTA has a boxed treatment. Scene motion presets include fade up, slide in, scale in, a softened reveal and staggered entry. The caption/disclosure remain visible in safe lower regions. The render uses the same master MP4 and immutable manifest for all platforms.

The reusable scene template keys include CTA button/badge; link in bio, check description, try it here, learn more, visit website, use the link below, see full details and explore now; product title, feature, bullet, lower third, tag and disclaimer frames; social outro, end card and link direction; and an optional Powered by Dewdora label. These are generic overlays, not tied to ElevenLabs. An editor can choose them in each scene, review the snapshot and master video, and request a revision. A scene-design revision rerenders video without regenerating narration unless script/voice changed. Locks and final approval checks still apply.

The optional voice override loads ElevenLabs voices only when expanded. Otherwise the configured default voice is used. No new environment variable or migration is required for this visual update. Deployment `.env`, host settings, OAuth credentials and other deployment configuration are unchanged.
