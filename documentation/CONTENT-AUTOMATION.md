# Content automation

1. Add an active brand and product with your affiliate URL. Optionally create a dated campaign with priority and a monthly target in **Campaigns**. Approve official source documents and upload screenshots or screen recordings in **Affiliate Agent → Media**.
2. Configure real OpenAI and ElevenLabs providers in the server environment. In **Automation**, choose timezone, generation time/days, 2–3 videos per day, horizon, duration and publishing slots. Save **Enable daily generation**.
3. Run the production queue workers and minute scheduler described in `PRODUCTION-WORKERS.md`. The scheduler queues daily planning, which chooses an underrepresented content type and an active product. Research reads approved sources, the script provider drafts content, media is selected, voice and one FFmpeg master are generated, QA runs, and Review receives the result.
4. Review every item. Generation never marks a version FINAL_APPROVED. If sources, media or provider keys are missing, inspect the failed generation job and correct setup.
5. To try without paid APIs, use the explicitly labelled mock providers. Mock videos use a test tone and cannot be sent through production social adapters.

Current planner avoids exact recent title duplicates and counts recent product usage; it does not infer performance metrics from social networks until those APIs are connected for analytics import.
