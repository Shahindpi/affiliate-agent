# Scheduling and publishing

1. In **Automation**, set local publishing slots (for example 09:00, 14:00, 20:00) and a late approval policy. The default uses the next future slot.
2. Connect and enable social accounts. Enable production API publishing only with real OpenAI/ElevenLabs output and working provider credentials.
3. Approve the exact review version. Five platform records are reserved independently, using a connected API account when production is enabled and manual fallback otherwise.
4. Open the content detail to adjust a still-scheduled platform/account/time. Open **Publishing** to see scheduled, processing, success and manual action states. The worker handles only due records; successful destinations are never replayed.
5. Download the approved master and copy/export per-platform metadata even if APIs are configured. Confirm a manual publication with its actual public URL.
6. A platform upload can succeed while the network response is lost. Such ambiguous errors become `MANUAL_ACTION_REQUIRED`; verify remotely before any manual retry to avoid duplicates. Processing records poll without re-uploading.

Production social sends require a reachable public HTTPS Laravel host for Meta video fetching and the provider's platform permissions. `MOCK_PUBLISHED`, `API_REVIEW_REQUIRED`, `PROCESSING`, and TikTok draft upload are distinct from public success.
