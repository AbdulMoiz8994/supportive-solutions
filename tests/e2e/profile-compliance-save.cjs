/**
 * Local browser check — Caregiver profile edit + client Compliance EVV persist.
 * 2FA off locally (TWO_FACTOR_ENFORCED=false). Run: node tests/e2e/profile-compliance-save.cjs
 */
const { chromium } = require('playwright');
const BASE = 'http://127.0.0.1:8000';
const EMAIL = 'super@beydountech.com', PASS = 'super123';
let passed = 0, failed = 0;
const log = (m) => { console.log(`  [PASS] ${m}`); passed++; };
const fail = (m) => { console.error(`  [FAIL] ${m}`); failed++; };
const h = (t) => console.log(`\n── ${t} ──`);

(async () => {
    const b = await chromium.launch({ headless: true });
    const page = await (await b.newContext({ viewport: { width: 1440, height: 980 } })).newPage();
    const errs = []; page.on('pageerror', e => errs.push(e.message));

    await page.goto(`${BASE}/signin`); await page.waitForLoadState('networkidle');
    await page.fill('input[type="email"]', EMAIL); await page.fill('input[type="password"]', PASS);
    await page.click('button[type="submit"]'); await page.waitForLoadState('networkidle');
    if (/signin|two-factor/.test(page.url())) { fail('login failed → ' + page.url()); await b.close(); return done(); }
    log('logged in → ' + page.url());

    // ── Caregiver profile: Personal + Emergency Contact ──
    h('Caregiver profile edit (caregivers/1, Personal tab)');
    const cg = `${BASE}/caregivers/1`;
    await page.goto(cg); await page.waitForLoadState('networkidle');
    // Personal tab is default; click its Edit button
    const editBtn = page.locator('button:has-text("Edit")').first();
    await editBtn.click(); await page.waitForTimeout(400);
    const phone = '(313) 555-0' + Date.now().toString().slice(-3);
    await page.locator('input[name="phone"]').first().fill(phone);
    const emName = 'QA Contact ' + Date.now().toString().slice(-4);
    const em = page.locator('input[name="emergency_contact_name"]').first();
    if (await em.count() > 0) await em.fill(emName); else fail('emergency_contact_name input missing (not editable)');
    await page.locator('button:has-text("Save changes")').first().click();
    await page.waitForLoadState('networkidle'); await page.waitForTimeout(500);
    let body = await page.locator('body').innerText();
    body.includes(phone) ? log(`Personal phone persisted → ${phone}`) : fail('Personal phone did NOT persist');
    body.includes(emName) ? log(`Emergency Contact name persisted → ${emName}`) : fail('Emergency Contact did NOT persist');

    // ── Client Compliance tab: HHAeXchange Verification EVV status ──
    h('Client Compliance tab — HHAeXchange Verification EVV');
    const url = `${BASE}/clients/1?tab=compliance`;
    const openComp = async () => {
        await page.goto(url); await page.waitForLoadState('networkidle');
        const t = page.locator('button', { hasText: 'Compliance Forms' }).first();
        if (await t.count() > 0) { await t.click(); await page.waitForTimeout(500); }
    };
    await openComp();
    const panel = page.locator('div.rounded-2xl:has(> form h3:text-is("HHAeXchange Verification"))').first();
    if (await panel.getByRole('button', { name: 'Edit' }).count() === 0) { fail('HHAeXchange Verification Edit missing'); }
    else {
        await panel.getByRole('button', { name: 'Edit' }).first().click(); await page.waitForTimeout(300);
        await panel.locator('select[name="evv_status"]').first().selectOption('Active — clock-in / out required');
        await panel.getByRole('button', { name: 'Save' }).first().click();
        await page.waitForLoadState('networkidle'); await page.waitForTimeout(400);
        (await page.locator('text=Changes saved').first().isVisible().catch(() => false))
            ? log('Compliance EVV: "Changes saved" banner') : fail('Compliance EVV: no banner');
        await openComp();
        (await panel.innerText()).includes('Active — clock-in')
            ? log('Compliance EVV status persisted → Active') : fail('Compliance EVV did NOT persist');
    }

    errs.length === 0 ? log('no console/page errors') : fail('JS errors: ' + errs.slice(0,3).join(' | '));
    await b.close(); done();

    function done() { console.log(`\n  RESULT: ${passed} passed · ${failed} failed\n`); if (failed) process.exit(1); }
})().catch(e => { console.error('FATAL', e.message); process.exit(1); });
