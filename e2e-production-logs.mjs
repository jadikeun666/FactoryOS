// e2e-production-logs.mjs
//
// Skrip E2E ad-hoc untuk mendiagnosis error di halaman Production Logs
// (Index, Create, Show, Edit). BUKAN bagian dari test suite permanen —
// ini alat diagnostik sekali pakai, taruh di root project laravel/ dan
// jalankan manual.
//
// SETUP (sekali saja):
//   npm install -D playwright
//   npx playwright install chromium
//
// JALANKAN (pastikan `php artisan serve` sudah aktif di terminal lain):
//   node e2e-production-logs.mjs
//
// Ubah kredensial login & base URL di bawah kalau perlu.

import { chromium } from 'playwright';

const BASE_URL = 'http://127.0.0.1:8000';
const LOGIN_EMAIL = 'admin@factoryos.local';
const LOGIN_PASSWORD = 'password'; // ganti sesuai seeder Anda

const report = [];

function logIssue(page, type, detail) {
  report.push({ page, type, detail: String(detail).slice(0, 500) });
}

async function visitAndInspect(browser, path, label) {
  const context = await browser.newContext();
  const page = await context.newPage();

  const consoleErrors = [];
  const pageErrors = [];
  const failedRequests = [];

  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('pageerror', (err) => {
    pageErrors.push(err.message + '\n' + (err.stack ?? ''));
  });
  page.on('requestfailed', (req) => {
    failedRequests.push(`${req.method()} ${req.url()} — ${req.failure()?.errorText}`);
  });
  page.on('response', async (res) => {
    if (res.status() >= 400) {
      failedRequests.push(`${res.status()} ${res.request().method()} ${res.url()}`);
    }
  });

  // --- Login ---
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[type=email]', LOGIN_EMAIL);
  await page.fill('input[type=password]', LOGIN_PASSWORD);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
    page.click('button[type=submit]'),
  ]);

  // --- Visit target page ---
  const url = `${BASE_URL}${path}`;
  let navError = null;
  try {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 15000 });
  } catch (e) {
    navError = e.message;
  }

  await page.waitForTimeout(1000); // beri waktu Vue/Inertia mount & render

  const screenshotPath = `e2e-screenshot-${label}.png`;
  await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});

  const bodyText = await page.textContent('body').catch(() => '');
  const isBlank = !bodyText || bodyText.trim().length < 10;

  if (navError) logIssue(label, 'navigation_error', navError);
  if (isBlank) logIssue(label, 'blank_page', 'Body text kosong/hampir kosong — kemungkinan Vue gagal mount.');
  consoleErrors.forEach((e) => logIssue(label, 'console_error', e));
  pageErrors.forEach((e) => logIssue(label, 'uncaught_exception', e));
  failedRequests.forEach((e) => logIssue(label, 'failed_request', e));

  if (consoleErrors.length === 0 && pageErrors.length === 0 && failedRequests.length === 0 && !isBlank && !navError) {
    report.push({ page: label, type: 'OK', detail: `Halaman ${path} render tanpa error terdeteksi.` });
  }

  await context.close();
}

async function main() {
  const browser = await chromium.launch();

  // Ambil salah satu ID production log yang valid dulu via halaman index,
  // supaya Show/Edit tidak 404 karena ID tidak ada.
  const context = await browser.newContext();
  const page = await context.newPage();
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[type=email]', LOGIN_EMAIL);
  await page.fill('input[type=password]', LOGIN_PASSWORD);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
    page.click('button[type=submit]'),
  ]);
  await page.goto(`${BASE_URL}/production-logs`, { waitUntil: 'networkidle' });
  const firstDetailHref = await page
    .locator('a:has-text("Detail")')
    .first()
    .getAttribute('href')
    .catch(() => null);
  await context.close();

  const sampleId = firstDetailHref ? firstDetailHref.split('/').filter(Boolean).pop() : null;

  await visitAndInspect(browser, '/production-logs', 'index');
  await visitAndInspect(browser, '/production-logs/create', 'create');

  if (sampleId) {
    await visitAndInspect(browser, `/production-logs/${sampleId}`, 'show');
    await visitAndInspect(browser, `/production-logs/${sampleId}/edit`, 'edit');
  } else {
    report.push({
      page: 'show/edit',
      type: 'SKIPPED',
      detail: 'Tidak menemukan link "Detail" di halaman index — tidak bisa test Show/Edit dengan ID nyata.',
    });
  }

  await browser.close();

  console.log('\n========== LAPORAN E2E PRODUCTION LOGS ==========\n');
  for (const item of report) {
    console.log(`[${item.page}] (${item.type})`);
    console.log(`  ${item.detail}`);
    console.log('');
  }
  console.log('===================================================');
  console.log(`Screenshot disimpan sebagai: e2e-screenshot-<nama-halaman>.png di direktori ini.`);

  const hasErrors = report.some((r) => !['OK', 'SKIPPED'].includes(r.type));
  process.exit(hasErrors ? 1 : 0);
}

main().catch((err) => {
  console.error('Skrip E2E gagal jalan:', err);
  process.exit(1);
});
