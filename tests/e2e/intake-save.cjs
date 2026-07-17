/**
 * Local browser check — Intake & Screening panels now persist.
 * 2FA is off locally (TWO_FACTOR_ENFORCED=false), so login is simple.
 * Run: node tests/e2e/intake-save.cjs
 */
const { chromium } = require('playwright');
const BASE = 'http://127.0.0.1:8000';
const EMAIL = 'super@beydountech.com', PASS = 'super123';
let passed = 0, failed = 0;
const log = (m) => { console.log(`  [PASS] ${m}`); passed++; };
const fail = (m) => { console.error(`  [FAIL] ${m}`); failed++; };

async function openTab(page, url) {
    await page.goto(url); await page.waitForLoadState('networkidle');
    const tab = page.locator('button', { hasText: 'Intake & Screening' }).first();
    if (await tab.count() > 0) { await tab.click(); await page.waitForTimeout(400); }
}

(async () => {
    const b = await chromium.launch({ headless: true });
    const page = await (await b.newContext({ viewport: { width: 1440, height: 980 } })).newPage();
    const errs = [];
    page.on('pageerror', e => errs.push(e.message));

    await page.goto(`${BASE}/signin`); await page.waitForLoadState('networkidle');
    await page.fill('input[type="email"]', EMAIL);
    await page.fill('input[type="password"]', PASS);
    await page.click('button[type="submit"]'); await page.waitForLoadState('networkidle');
    if (/signin|two-factor/.test(page.url())) { fail('login failed → ' + page.url()); await b.close(); return done(); }
    log('logged in (no 2FA) → ' + page.url());

    const url = `${BASE}/clients/1?tab=intake`;

    // Referral panel round-trip
    await openTab(page, url);
    const panel = page.locator('div.rounded-2xl:has(> form h3:text-is("Referral"))').first();
    if (await panel.getByRole('button', { name: 'Edit' }).count() === 0) { fail('Referral Edit button missing'); }
    else {
        await panel.getByRole('button', { name: 'Edit' }).first().click();
        await page.waitForTimeout(300);
        const field = panel.locator('input[name="referred_by"]').first();
        const val = 'Dr. Test ' + Date.now().toString().slice(-5);
        await field.fill(val);
        await panel.getByRole('button', { name: 'Save' }).first().click();
        await page.waitForLoadState('networkidle'); await page.waitForTimeout(400);
        (await page.locator('text=Changes saved').first().isVisible().catch(() => false))
            ? log('Referral: "Changes saved" banner') : fail('Referral: no banner');
        await openTab(page, url);
        (await panel.innerText()).includes(val)
            ? log(`Referral "Referred by" persisted after reload → ${val}`)
            : fail('Referral did NOT persist');
    }

    // Services Requested round-trip (check a box, save, reload)
    await openTab(page, url);
    const sp = page.locator('div.rounded-2xl:has(> form h3:text-is("Services Requested"))').first();
    if (await sp.getByRole('button', { name: 'Edit' }).count() > 0) {
        await sp.getByRole('button', { name: 'Edit' }).first().click();
        await page.waitForTimeout(300);
        const bathing = sp.locator('input[type="checkbox"][value="Bathing"]').first();
        const was = await bathing.isChecked();
        if (was) await bathing.uncheck(); else await bathing.check();
        await sp.getByRole('button', { name: 'Save' }).first().click();
        await page.waitForLoadState('networkidle'); await page.waitForTimeout(400);
        await openTab(page, url);
        await sp.getByRole('button', { name: 'Edit' }).first().click();
        await page.waitForTimeout(300);
        const now = await sp.locator('input[type="checkbox"][value="Bathing"]').first().isChecked();
        (now !== was) ? log(`Services Requested: "Bathing" toggled ${was}→${now} and persisted`)
                      : fail('Services Requested did NOT persist');
    } else fail('Services Requested Edit button missing');

    errs.length === 0 ? log('no console/page errors') : fail('JS errors: ' + errs.join(' | '));
    await b.close(); done();

    function done() {
        console.log(`\n  RESULT: ${passed} passed · ${failed} failed\n`);
        if (failed) process.exit(1);
    }
})().catch(e => { console.error('FATAL', e.message); process.exit(1); });
