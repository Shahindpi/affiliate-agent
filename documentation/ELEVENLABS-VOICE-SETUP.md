# ElevenLabs voice setup

1. Get an API key and available voice in your [ElevenLabs account](https://elevenlabs.io/). Set `AGENT_VOICE_PROVIDER=elevenlabs`, `ELEVENLABS_API_KEY=...`, and optionally `ELEVENLABS_VOICE_ID=...`/`ELEVENLABS_MODEL=...` in Laravel `.env`.
2. Run `php artisan config:clear` and restart workers. In **Affiliate Agent → Automation**, choose a default voice from the account list and play its preview. Use **Test ElevenLabs** on Setup to verify the key against ElevenLabs.
3. Create a real video or change `voice.voice_id` on a reviewed version. VoiceAgent generates narration from the approved script. If neither script nor voice changes on a revision, the previous immutable voice asset is reused.
4. Check generated audio and timing in Review. A mock provider produces a labelled test tone and cannot publish via connected social APIs.
5. For voice quota, authorization, or duration errors, check the queue failure and ElevenLabs account limits; shorten script or adjust scenes within 15–45 seconds.
