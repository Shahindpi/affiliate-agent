import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true });
const base = process.env.NO_GA_BASE_URL || 'http://127.0.0.1:3001';
try {
  const page = await browser.newPage();
  const errors = [], tagRequests = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => {
    if (message.text().includes('Encountered a script tag while rendering React component') || message.text().includes('Cannot render a sync or defer <script> outside the main document')) errors.push(message.text());
  });
  page.on('request', request => {
    if (request.url().includes('googletagmanager.com/gtag/js')) tagRequests.push(request.url());
  });
  const response = await page.goto(base + '/');
  if (response.status() !== 200) throw Error(`Homepage returned ${response.status()}`);
  await page.getByRole('heading', { name: 'Latest Affiliate Products' }).waitFor();
  if (await page.locator('#dewdora-theme').count()) throw Error('Theme inline script still rendered');
  await page.getByRole('region', { name: 'Latest Affiliate Products' }).getByRole('article').first().locator('a[href^="/products/"]').first().click();
  await page.getByRole('heading', { level: 1 }).waitFor();
  if (tagRequests.length || errors.length) throw Error(JSON.stringify({ tagRequests, errors }));
  console.log('PASS Homepage and product navigation without GA ID, tag request, or script error');
} finally { await browser.close(); }
