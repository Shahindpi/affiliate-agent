import { chromium } from "playwright";
import fs from "node:fs";
import path from "node:path";

const base = process.env.PERF_BASE_URL || "http://127.0.0.1:3000";
const email = process.env.PERF_ADMIN_EMAIL;
const password = process.env.PERF_ADMIN_PASSWORD;
const output = path.resolve(process.env.PERF_OUTPUT || "test-results/performance.json");
const routes = [
  ["homepage", "/"], ["products", "/products"], ["product", "/products/northstar-writing-desk"],
  ["categories", "/categories"], ["category", "/categories/ai-tools"], ["posts", "/posts"],
  ["post", "/posts/choosing-an-ai-writing-companion"], ["login", "/auth/login"],
];
const adminRoutes = [["admin", "/admin"], ["admin_products", "/admin/products"], ["admin_posts", "/admin/posts"], ["admin_analytics", "/admin/analytics"]];

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
await page.addInitScript(() => {
  window.__perfCls = 0;
  new PerformanceObserver(list => {
    for (const entry of list.getEntries()) if (!entry.hadRecentInput) window.__perfCls += entry.value;
  }).observe({ type: "layout-shift", buffered: true });
});

async function measure(name, route) {
  const response = await page.goto(base + route, { waitUntil: "networkidle" });
  if (!response || response.status() >= 400) throw new Error(`${route} returned ${response?.status()}`);
  await page.waitForTimeout(100);
  return page.evaluate(({ name, route }) => {
    const navigation = performance.getEntriesByType("navigation")[0];
    const resources = performance.getEntriesByType("resource");
    const sum = type => resources.filter(item => type(item)).reduce((total, item) => total + (item.transferSize || item.encodedBodySize || 0), 0);
    return {
      name, route, response_end_ms: Math.round(navigation.responseEnd), dom_content_loaded_ms: Math.round(navigation.domContentLoadedEventEnd),
      load_ms: Math.round(navigation.loadEventEnd), transfer_bytes: Math.round((navigation.transferSize || navigation.encodedBodySize || 0) + sum(() => true)),
      js_bytes: Math.round(sum(item => item.initiatorType === "script")), image_bytes: Math.round(sum(item => item.initiatorType === "img")),
      requests: resources.length + 1, cls: Number((window.__perfCls || 0).toFixed(4)),
    };
  }, { name, route });
}

try {
  const results = [];
  for (const [name, route] of routes) results.push(await measure(name, route));
  if (email && password) {
    await page.goto(base + "/auth/login");
    await page.locator("input[name=email]").fill(email); await page.locator("input[name=password]").fill(password);
    await page.getByRole("button", { name: "Sign In", exact: true }).click(); await page.waitForURL("**/admin");
    for (const [name, route] of adminRoutes) results.push(await measure(name, route));
  }
  fs.mkdirSync(path.dirname(output), { recursive: true });
  fs.writeFileSync(output, JSON.stringify({ generated_at: new Date().toISOString(), base, results }, null, 2));
  console.table(results);
} finally { await browser.close(); }
