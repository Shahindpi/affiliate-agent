import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true });
try {
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const api = 'http://127.0.0.1:8000/api/v1';
  const login = await page.request.post(`${api}/auth/login`, { data: { email: process.env.E2E_ADMIN_EMAIL, password: process.env.E2E_ADMIN_PASSWORD } });
  if (!login.ok()) throw Error(`Admin login failed: ${login.status()}`);
  const token = (await login.json()).data.token;
  const headers = { Authorization: `Bearer ${token}` };
  const meta = Object.fromEntries(['pinterest', 'instagram', 'tiktok', 'facebook', 'youtube'].map(platform => [platform, { title: 'Test review', caption: 'Watch the demo', description: 'A short demo', hashtags: ['#demo'], affiliate_url: 'https://example.test/offer', cta: 'Try the tool', disclosure: 'Affiliate link: I may earn a commission.' }]));
  const snapshot = { script: 'Turn text into clear speech with this tool.', captions: 'Turn text into clear speech with this tool.', cta: 'Try the tool', disclosure: 'Affiliate link: I may earn a commission.', voice: { voice_id: null }, scenes: [{ id: 'intro', text: 'Create a voice', duration: 7.5, media_id: null }, { id: 'demo', text: 'Generate and download', duration: 7.5, media_id: null }], metadata: meta };
  const created = await page.request.post(`${api}/admin/affiliate-agent/contents`, { headers, data: { title: `Review acceptance ${Date.now()}`, snapshot } });
  if (created.status() !== 201) throw Error(`Create returned ${created.status()}: ${await created.text()}`);
  const id = (await created.json()).data.id;
  let content;
  for (let attempt = 0; attempt < 60; attempt++) {
    const response = await page.request.get(`${api}/admin/affiliate-agent/contents/${id}`, { headers });
    content = (await response.json()).data;
    if (content.status === 'REVIEW_PENDING') break;
    if (content.status === 'GENERATION_FAILED') throw Error('Render failed; inspect worker log.');
    await new Promise(resolve => setTimeout(resolve, 1000));
  }
  if (content?.status !== 'REVIEW_PENDING') throw Error('Master video did not enter review queue.');

  await page.goto('http://127.0.0.1:3000/auth/login');
  await page.locator('input[name=email]').fill(process.env.E2E_ADMIN_EMAIL);
  await page.locator('input[name=password]').fill(process.env.E2E_ADMIN_PASSWORD);
  await page.getByRole('button', { name: 'Sign In', exact: true }).click();
  await page.waitForURL('**/admin');
  await page.goto(`http://127.0.0.1:3000/admin/affiliate-agent/contents/${id}`);
  await page.getByText('Master video · v1', { exact: true }).waitFor();
  await page.getByLabel('Voiceover preview').waitFor();
  await page.getByText('Affiliate link: I may earn a commission.', { exact: true }).first().waitFor();
  await page.getByRole('button', { name: 'Final approve v1' }).click();
  await page.getByText('Version 1 is FINAL_APPROVED.').waitFor();
  await page.getByLabel('Feedback target').selectOption('metadata.youtube');
  await page.getByPlaceholder('Make the opening more direct. Keep the voice and closing scene unchanged.').fill('set metadata.youtube.title: Clearer YouTube tutorial');
  await page.getByRole('button', { name: 'Request revision of v1' }).click();
  await page.getByText('Master video · v2', { exact: true }).waitFor({ timeout: 30000 });
  if (errors.length) throw Error(`Browser page errors: ${errors.join('; ')}`);
  const after = (await (await page.request.get(`${api}/admin/affiliate-agent/contents/${id}`, { headers })).json()).data;
  if (after.status !== 'REVIEW_PENDING' || after.final_approved_version_id !== null) throw Error('Revised version retained the old approval.');
  console.log('PASS master render, review preview, final approval, scoped revision and invalidation');
} finally {
  await browser.close();
}
