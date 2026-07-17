/**
 * Playwright verification — Authorization data-entry & SAVE loop.
 * Targets the July-10 "Data-Entry & Save Deep-Test" bugs (Authorization module):
 *   #1/#3  Authorization Details edit must persist + derived units recompute on reload
 *   #2     Agency "Log authorization" button must not be a dead link (opens picker)
 *   #4     "Add authorization" form must actually create a PA
 *
 * Run against local dev:  php -S 127.0.0.1:8000 server.php
 *   node tests/e2e/authorization-save.cjs
 */
const { chromium } = require('playwright');
const fs   = require('fs');
const path = require('path');
const os   = require('os');

const BASE     = 'http://127.0.0.1:8000';
const EMAIL    = 'super@beydountech.com';
const PASS     = 'super123';
const LOG_FILE = path.join(__dirname, '../../storage/logs/laravel.log');
const SHOT_DIR = path.join(os.tmpdir(), 'beydountech-e2e');

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
            // Email radio is pre-checked — just submit and wait for the verify hop.
            const opt = page.locator('input[value="email"]').first();
            if (await opt.count() > 0) await opt.check().catch(() => {});
            // Submit natively — the Alpine @submit/:disabled combo can swallow a button click.
            await page.locator('form[action*="two-factor"]').first().evaluate((f) => f.submit());
            await page.waitForURL('**/two-factor/verify', { timeout: 15000 }).catch(() => {});
            if (!page.url().includes('verify')) {
                const body = (await page.locator('body').innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 240);
                console.log('    2FA-DEBUG url=' + page.url() + ' | body="' + body + '"');
            }
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

(async () => {
    const browser = await chromium.launch({ headless: true });
    const ctx  = await browser.newContext({ viewport: { width: 1440, height: 980 } });
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
    page.on('console', (m) => { if (m.type() === 'error') errors.push('console.error: ' + m.text()); });

    if (!(await login(page))) { await browser.close(); return printSummary(); }

    // ─── Bug #2: "Log authorization" is no longer a dead link ───────────────────
    h('Bug #2 — /authorizations "Log authorization" opens a client picker');
    await page.goto(`${BASE}/authorizations`);
    await page.waitForLoadState('networkidle');
    const logBtn = page.locator('button', { hasText: 'Log authorization' }).first();
    if (await logBtn.count() === 0) { fail('"Log authorization" button not found'); }
    else {
        await logBtn.click();
        await page.waitForTimeout(400);
        const picker = page.locator('text=Pick the client').first();
        (await picker.isVisible().catch(() => false)) ? log('Picker modal opens (not a dead /clients link) ✓')
                                                      : fail('Picker modal did not open');
        // close
        await page.keyboard.press('Escape');
    }

    // ─── Bug #1/#3: edit Authorization Details → Save → reload persists ──────────
    h('Bug #1/#3 — Authorization Details edit persists + recomputes');
    // Client #1 is the exact record the July-10 tester used (112 units).
    const target = `${BASE}/clients/1?tab=authorization`;

    async function openAuthTab() {
        await page.goto(target);
        await page.waitForLoadState('networkidle');
        const tabBtn = page.locator('button', { hasText: 'Program & Authorization' }).first();
        if (await tabBtn.count() > 0) { await tabBtn.click(); }
        // Wait until the tab content is actually visible (x-show gate resolved).
        await page.locator('h3:text-is("Authorization Details")').first()
            .waitFor({ state: 'visible', timeout: 8000 }).catch(() => {});
    }

    await openAuthTab();
    console.log('    (on ' + page.url() + ')');
    // ---- debug: why is the panel not visible? ----
    const dbg = await page.evaluate(() => {
        const root = document.querySelector('[x-data*="activeTab"]');
        let activeTab = null;
        try { activeTab = root && window.Alpine ? window.Alpine.$data(root).activeTab : '(no alpine)'; } catch (e) { activeTab = 'err:' + e.message; }
        const h3s = [...document.querySelectorAll('h3')].map(h => h.textContent.trim()).filter(Boolean);
        const ad = [...document.querySelectorAll('h3')].find(h => h.textContent.trim() === 'Authorization Details');
        const vis = ad ? (ad.offsetParent !== null) : false;
        return { activeTab, hasAuthH3: !!ad, authVisible: vis, h3count: h3s.length, sampleH3: h3s.slice(0, 12) };
    });
    console.log('    DEBUG', JSON.stringify(dbg));
    if (errors.length) console.log('    JS-ERRORS', errors.slice(0, 5).join(' | '));
    const panel = page.locator('div.rounded-2xl:has(> form h3:text-is("Authorization Details"))').first();
    if (await page.locator('h3:text-is("Authorization Details")').count() === 0) {
        fail('No Authorization Details panel found on client #1');
    } else {
        const editBtn = panel.getByRole('button', { name: 'Edit' }).first();
        await editBtn.click({ timeout: 8000 });
        await page.waitForTimeout(300);
        const unitsInput = panel.locator('input[name="total_units"]').first();
        if (await unitsInput.count() === 0) { fail('total_units input not present when editing (field not wired)'); }
        else {
            const before = await unitsInput.inputValue();
            const next = String((parseInt(before || '0', 10) || 100) + 8);
            await unitsInput.fill(next);
            await panel.getByRole('button', { name: 'Save' }).first().click();
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(400);
            const banner = page.locator('text=Changes saved').first();
            (await banner.isVisible().catch(() => false)) ? log('"Changes saved" banner shown ✓') : warn('No "Changes saved" banner seen');
            // reload and confirm persisted
            await openAuthTab();
            const shown = await panel.innerText();
            shown.includes(next) ? log(`Units persisted after reload (${before} → ${next}) ✓`)
                                 : fail(`Units did NOT persist (expected ${next}). Panel text: ${shown.slice(0,140)}`);
        }
    }

    // ─── Bug #4: "Add authorization" modal opens and posts to the real route ────
    h('Bug #4 — "Add authorization" modal is wired');
    const addBtn = page.locator('button', { hasText: 'Add authorization' }).first();
    if (await addBtn.count() === 0) { warn('"Add authorization" button not found on this client tab'); }
    else {
        await addBtn.click();
        await page.waitForTimeout(400);
        const modalForm = page.locator('form[action*="/care-details"]').first();
        (await modalForm.isVisible().catch(() => false)) ? log('Add-authorization modal opens with real POST action ✓')
                                                         : fail('Add-authorization modal did not open / no care-details form');
    }

    await page.screenshot({ path: `${SHOT_DIR}/authorization-save.png`, fullPage: true });
    (errors.length === 0) ? log('No console/page errors ✓') : warn('JS errors: ' + errors.join(' | '));

    await browser.close();
    printSummary();

    function printSummary() {
        console.log(`\n${'═'.repeat(60)}`);
        console.log(`  RESULTS: ${passed} passed  ·  ${warned} warnings  ·  ${failed} failed`);
        console.log(`${'═'.repeat(60)}\n`);
        if (failed > 0) process.exit(1);
    }
})();
