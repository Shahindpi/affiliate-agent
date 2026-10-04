# Pinterest setup

1. Create an app at [Pinterest Developers](https://developers.pinterest.com/) and request the product/API access required for creating video Pins. Set `PINTEREST_CLIENT_ID` and `PINTEREST_CLIENT_SECRET` in Laravel `.env`; register `https://YOUR-LARAVEL-HOST/api/v1/affiliate-agent/oauth/pinterest/callback` exactly. Clear Laravel config and restart workers.
2. In **Affiliate Agent → Social accounts → Pinterest**, connect and test the account. OAuth requests `boards:read`, `boards:write`, `pins:read`, `pins:write`, `user_accounts:read`. Reconnect an account that authorized the previous scope set.
3. Use **Pinterest destinations** to refresh real boards from the API. Select **AI Tools** or create it if needed; optionally select or create the **AI Voice & Audio** section. These names are examples, not IDs. The UI saves only IDs returned and verified by Pinterest. A board alone is valid; a section is optional.
4. Save an account default. Optionally add a brand, product, campaign or content override by its existing local ID. The most specific mapping wins: content, campaign, product, brand, account. Refresh the board/section list after changes. Give the account a public HTTPS cover image URL and enable publishing only after account verification.
5. Approve a real video, schedule Pinterest and inspect **Publishing** for Pin ID/URL. The adapter registers media, uploads video, checks processing and creates the Pin with the approved affiliate link and disclosure. If API approval or cover is missing, download the master and copy approved Pinterest metadata for manual publishing.

Official workflow: [Create boards and Pins](https://developers.pinterest.com/docs/work-with-organic-content-and-users/create-boards-and-pins/).
