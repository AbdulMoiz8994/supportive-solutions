/**
 * Playwright verification — the three new agency pages.
 * Targets: http://127.0.0.1:8000 (local Laravel dev server)
 * Handles 2FA automatically via storage/logs/laravel.log (MAIL_MAILER=log).
 *
 * Verifies: Authorizations, Background Checks, Compliance & Documents
 *   - page renders (auth ok, key headings + columns present)
 *   - Alpine interactivity works (chips/tabs) with no console/page errors
 */

const { chromium } = require('playwright');
const fs   = require('fs');
const path = require('path');
const os   = require('os');

const BASE     = 'http://127.0.0.1:8000';
const EMAIL    = 'super@beydountech.com';
const PASS     = 'super123';
const LOG_FILE = path.join(__dirname, '../../storage/logs/laravel.log');
const SHOT_DIR = path.join(os.tmpdir(), 'beydountech-e2e'); // Playwright auto-creates it

let passed = 0, failed = 0, warned = 0;
const log  = (m) => { console.log(`  [PASS] ${m}`); passed++; };
const fail = (m) => { console.error(`  [FAIL] ${m}`); failed++; };
const warn = (m) => { console.warn(`  [WARN] ${m}`); warned++; };
const h    = (t) => console.log(`\n${'─'.repeat(60)}\n  ${t}\n${'─'.repeat(60)}`);

function readOtpFromLog() {
    try {
        const c = fs.readFileSync(LOG_FILE, 'utf8');
        const m = [...c.matchAll(/verification code is:\s*\*?\*?(\d{6})\*?\*?/gi)];
        if (m.length) return m[m.length - 1][1];
    } catch (_) {}
    return null;
}

async function login(page) {
    h('LOGIN (automated 2FA via mail log)');
    await page.goto(`${BASE}/signin`);
    await page.waitForLoadState('networkidle');
    await page.fill('input[name="email"], input[type="email"]', EMAIL);
    await page.fill('input[name="password"], input[type="password"]', PASS);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    if (page.url().includes('two-factor')) {
        if (page.url().includes('two-factor/choice')) {
            const opt = page.locator('input[value="email"]').first();
            if (await opt.count() > 0) await opt.click();
            const send = page.locator('button[type="submit"]').first();
            if (await send.count() > 0) await send.click();
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(1200);
        }
        if (!page.url().includes('two-factor/verify')) {
            await page.goto(`${BASE}/two-factor/verify`); await page.waitForLoadState('networkidle');
        }
        let otp = null;
        const dbg = page.locator('.font-mono.text-lg.tracking-widest').first();
        if (await dbg.count() > 0) otp = (await dbg.textContent())?.replace(/\s/g, '').trim();
        if (!otp || otp.length !== 6) otp = readOtpFromLog();
        if (!otp || otp.length !== 6) { fail('Could not obtain OTP'); return false; }
        const boxes = page.locator('input.otp-input');
        if (await boxes.count() === 6) {
            for (let i = 0; i < 6; i++) { await boxes.nth(i).fill(otp[i]); await page.waitForTimeout(50); }
            if (page.url().includes('two-factor/verify')) {
                const b = page.locator('form button[type="submit"]').first();
                if (await b.count() > 0 && await b.isVisible().catch(() => false)) await b.click().catch(() => {});
            }
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(800);
        }
    }
    if (/signin|login|two-factor/.test(page.url())) { fail('Still on auth page: ' + page.url()); return false; }
    log('Authenticated → ' + page.url());
    return true;
}

// Per-page console/page error collector
function attachErrorWatch(page, bucket) {
    page.on('pageerror', (e) => bucket.push('pageerror: ' + e.message));
    page.on('console', (m) => { if (m.type() === 'error') bucket.push('console.error: ' + m.text()); });
}

(async () => {
    let browser;
    try { browser = await chromium.launch({ headless: true }); }
    catch (err) {
        if (String(err.message || err).includes("Executable doesn't exist"))
            console.error('\nPlaywright Chromium missing. Run: npx playwright install chromium\n');
        throw err;
    }
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 980 } });
    const page = await ctx.newPage();
    const errors = [];
    attachErrorWatch(page, errors);

    if (!(await login(page))) { await browser.close(); return printSummary(); }

    // ─── 1. Authorizations ───────────────────────────────────────────────────
    h('Authorizations  (/authorizations)');
    let before = errors.length;
    const r1 = await page.goto(`${BASE}/authorizations`);
    await page.waitForLoadState('networkidle');
    (r1 && r1.status() === 200) ? log('HTTP 200') : fail('HTTP ' + (r1 && r1.status()));
    (await page.locator('h1', { hasText: 'Authorizations' }).count() > 0) ? log('Heading "Authorizations" ✓') : fail('Heading missing');
    (await page.locator('text=Active authorizations').count() > 0) ? log('KPI "Active authorizations" ✓') : fail('KPI missing');
    (await page.locator('th', { hasText: 'Expires / Reassess' }).count() > 0) ? log('Table column "Expires / Reassess" ✓') : fail('Table column missing');
    // interactivity: click the MICH chip
    const michChip = page.locator('button', { hasText: /^MICH$/ }).first();
    if (await michChip.count() > 0) { await michChip.click(); await page.waitForTimeout(400); log('Clicked MICH chip — no crash ✓'); }
    else warn('MICH chip not found');
    await page.screenshot({ path: `${SHOT_DIR}/page-authorizations.png`, fullPage: true });
    (errors.length === before) ? log('No console/page errors ✓') : fail('JS errors: ' + errors.slice(before).join(' | '));

    // ─── 2. Background Checks ─────────────────────────────────────────────────
    h('Background Checks  (/background-checks)');
    before = errors.length;
    const r2 = await page.goto(`${BASE}/background-checks`);
    await page.waitForLoadState('networkidle');
    (r2 && r2.status() === 200) ? log('HTTP 200') : fail('HTTP ' + (r2 && r2.status()));
    (await page.locator('h1', { hasText: 'Background Checks' }).count() > 0) ? log('Heading "Background Checks" ✓') : fail('Heading missing');
    for (const col of ['CHAMPS', 'ICHAT', 'SAM.gov', 'OIG LEIE']) {
        (await page.locator('th', { hasText: col }).count() > 0) ? log(`Matrix column "${col}" ✓`) : fail(`Column "${col}" missing`);
    }
    (await page.locator('text=Monthly SAM.gov + OIG LEIE batch').count() > 0) ? log('Batch banner ✓') : warn('Batch banner not found');
    const flaggedChip = page.locator('button', { hasText: /^Flagged$/ }).first();
    if (await flaggedChip.count() > 0) { await flaggedChip.click(); await page.waitForTimeout(400); log('Clicked Flagged chip — no crash ✓'); }
    await page.screenshot({ path: `${SHOT_DIR}/page-background-checks.png`, fullPage: true });
    (errors.length === before) ? log('No console/page errors ✓') : fail('JS errors: ' + errors.slice(before).join(' | '));

    // ─── 3. Compliance & Documents ────────────────────────────────────────────
    h('Compliance & Documents  (/compliance)');
    before = errors.length;
    const r3 = await page.goto(`${BASE}/compliance`);
    await page.waitForLoadState('networkidle');
    (r3 && r3.status() === 200) ? log('HTTP 200') : fail('HTTP ' + (r3 && r3.status()));
    (await page.locator('h1', { hasText: 'Compliance & Documents' }).count() > 0) ? log('Heading ✓') : fail('Heading missing');
    (await page.locator('button', { hasText: 'Monthly Compliance' }).count() > 0) ? log('Tab "Monthly Compliance" ✓') : fail('Tab missing');
    (await page.locator('button', { hasText: 'Document Hub' }).count() > 0) ? log('Tab "Document Hub" ✓') : fail('Tab missing');
    (await page.locator('text=compliance progress').count() > 0) ? log('Progress card visible (Monthly tab default) ✓') : warn('Progress card not visible');
    // switch to Document Hub
    await page.locator('button', { hasText: 'Document Hub' }).first().click();
    await page.waitForTimeout(500);
    (await page.locator('text=Verification Queue').first().isVisible().catch(() => false)) ? log('Document Hub → Verification Queue shows ✓') : fail('Verification Queue not shown after tab switch');
    (await page.locator('text=Needs attention').count() > 0) ? log('Document Hub → Needs attention shows ✓') : warn('Needs attention not found');
    // open upload modal
    await page.locator('button', { hasText: 'Upload Document' }).first().click();
    await page.waitForTimeout(400);
    (await page.locator('text=Upload Compliance Document').first().isVisible().catch(() => false)) ? log('Upload modal opens ✓') : warn('Upload modal did not open');
    await page.screenshot({ path: `${SHOT_DIR}/page-compliance.png`, fullPage: true });
    (errors.length === before) ? log('No console/page errors ✓') : fail('JS errors: ' + errors.slice(before).join(' | '));

    await browser.close();
    printSummary();

    function printSummary() {
        console.log(`\n${'═'.repeat(60)}`);
        console.log(`  RESULTS: ${passed} passed  ·  ${warned} warnings  ·  ${failed} failed`);
        console.log(`  Screenshots: ${SHOT_DIR}\\page-*.png`);
        console.log(`${'═'.repeat(60)}\n`);
        if (failed > 0) process.exit(1);
    }
})();
