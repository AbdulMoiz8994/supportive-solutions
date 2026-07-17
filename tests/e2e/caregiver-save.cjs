/**
 * Local browser check — Caregiver Assignment tab panels now persist.
 * 2FA off locally (TWO_FACTOR_ENFORCED=false). Client #1 has a seeded assignment.
 * Run: node tests/e2e/caregiver-save.cjs
 */
const { chromium } = require('playwright');
const BASE = 'http://127.0.0.1:8000';
const EMAIL = 'super@beydountech.com', PASS = 'super123';
let passed = 0, failed = 0;
const log = (m) => { console.log(`  [PASS] ${m}`); passed++; };
const fail = (m) => { console.error(`  [FAIL] ${m}`); failed++; };

async function openTab(page, url) {
    await page.goto(url); await page.waitForLoadState('networkidle');
    const tab = page.locator('button', { hasText: 'Caregiver Assignment' }).first();
    if (await tab.count() > 0) { await tab.click(); await page.waitForTimeout(400); }
}

async function roundtrip(page, url, title, editValue) {
    await openTab(page, url);
    const panel = page.locator(`div.rounded-2xl:has(> form h3:text-is("${title}"))`).first();
    if (await panel.getByRole('button', { name: 'Edit' }).count() === 0) { fail(`${title}: Edit button missing`); return; }
    await panel.getByRole('button', { name: 'Edit' }).first().click(); await page.waitForTimeout(300);
    await editValue(panel);
    await panel.getByRole('button', { name: 'Save' }).first().click();
    await page.waitForLoadState('networkidle'); await page.waitForTimeout(400);
    (await page.locator('text=Changes saved').first().isVisible().catch(() => false))
        ? log(`${title}: "Changes saved" banner`) : fail(`${title}: no banner`);
    return panel;
}

(async () => {
    const b = await chromium.launch({ headless: true });
    const page = await (await b.newContext({ viewport: { width: 1440, height: 980 } })).newPage();
    const errs = []; page.on('pageerror', e => errs.push(e.message));

    await page.goto(`${BASE}/signin`); await page.waitForLoadState('networkidle');
    await page.fill('input[type="email"]', EMAIL); await page.fill('input[type="password"]', PASS);
    await page.click('button[type="submit"]'); await page.waitForLoadState('networkidle');
    if (/signin|two-factor/.test(page.url())) { fail('login failed → ' + page.url()); await b.close(); return done(); }
    log('logged in → ' + page.url());

    const url = `${BASE}/clients/1?tab=caregiver`;

    // Assignment Details — Relationship select
    await roundtrip(page, url, 'Assignment Details', async (panel) => {
        await panel.locator('select[name="relationship"]').first().selectOption('Friend');
    });
    await openTab(page, url);
    let p = page.locator('div.rounded-2xl:has(> form h3:text-is("Assignment Details"))').first();
    (await p.innerText()).includes('Friend') ? log('Assignment Details: relationship persisted → Friend')
                                               : fail('Assignment Details did NOT persist');

    // Live-In Exemption — status select
    await roundtrip(page, url, 'Live-In Exemption', async (panel) => {
        await panel.locator('select[name="live_in_exemption_status"]').first().selectOption('Pending');
    });
    await openTab(page, url);
    p = page.locator('div.rounded-2xl:has(> form h3:text-is("Live-In Exemption"))').first();
    (await p.innerText()).includes('Pending') ? log('Live-In Exemption: status persisted → Pending')
                                              : fail('Live-In Exemption did NOT persist');

    // Pay Eligibility — hourly rate
    const rate = '17.25';
    await roundtrip(page, url, 'Pay Eligibility', async (panel) => {
        await panel.locator('input[name="billing_rate"]').first().fill(rate);
    });
    await openTab(page, url);
    p = page.locator('div.rounded-2xl:has(> form h3:text-is("Pay Eligibility"))').first();
    (await p.innerText()).includes('17.25') ? log('Pay Eligibility: rate persisted → $17.25')
                                            : fail('Pay Eligibility did NOT persist');

    errs.length === 0 ? log('no console/page errors') : fail('JS errors: ' + errs.join(' | '));
    await b.close(); done();

    function done() { console.log(`\n  RESULT: ${passed} passed · ${failed} failed\n`); if (failed) process.exit(1); }
})().catch(e => { console.error('FATAL', e.message); process.exit(1); });
