# OpenAI setup

1. Obtain an API key in the [OpenAI platform](https://platform.openai.com/api-keys). Set `AGENT_AI_PROVIDER=openai`, `OPENAI_API_KEY=...`, and `AGENT_AI_MODEL=...` in Laravel `.env`.
2. Optionally set input/output cost estimates per million tokens. Never put the key in `NEXT_PUBLIC_` variables.
3. Run `php artisan config:clear`, restart the generation and revision queue workers, and use **Affiliate Agent → Setup → Test OpenAI** to verify the key and selected model against the provider.
4. Upload media, approve official source knowledge, turn on daily generation, and inspect Review. The controlled ScriptAgent requests JSON from OpenAI and records usage; RevisionAgent uses the same provider for natural-language feedback.
5. If JSON validation fails or quota/rate errors occur, inspect Laravel failed jobs and usage, correct the provider configuration and retry the queued job. No incomplete version is approved automatically.
