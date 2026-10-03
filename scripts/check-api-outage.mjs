import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true });
try {
  const page = await browser.newPage();
  const response = await page.goto('http://127.0.0.1:3002/');
  if (response.status() !== 200) throw Error(`Failure state returned HTTP ${response.status()}`);
  await page.getByRole('heading', { name: 'Homepage temporarily unavailable' }).waitFor();
  await page.getByRole('button', { name: 'Try again' }).click();
  await page.getByRole('heading', { name: 'Homepage temporarily unavailable' }).waitFor();
  console.log('PASS Upstream failure renders a retryable homepage state');
} finally { await browser.close(); }
