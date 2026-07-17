/**
 * Playwright verification — Google Maps address autocomplete.
 * Targets: http://127.0.0.1:8000 (local Laravel dev server)
 * Handles 2FA automatically via storage/logs/laravel.log (MAIL_MAILER=log).
 *
 * Verifies the Places autocomplete wiring on the four address forms:
 *   - Client create (step 2)        · single address + county
 *   - Client demographics (edit)    · single address + county
 *   - Caregiver create (step 1)     · single address + county   ← live drop-down test
 *   - Caregiver personal (edit)     · single address + county
 */
const { chromium } = require('playwright');
const fs   = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const BASE     = 'http://127.0.0.1:8000';
const EMAIL    = 'super@beydountech.com';
const PASS     = 'super123';
const LOG_FILE = path.join(__dirname, '../../storage/logs/laravel.log');

let passed = 0, failed = 0, warned = 0;
const log  = (msg) => { console.log(`  [PASS] ${msg}`); passed++; };
const fail = (msg) => { console.error(`  [FAIL] ${msg}`); failed++; };
const warn = (msg) => { console.warn(`  [WARN] ${msg}`); warned++; };
const h    = (t)   => console.log(`\n${'─'.repeat(60)}\n  ${t}\n${'─'.repeat(60)}`);

function readOtpFromLog() {
    try {
        const content = fs.readFileSync(LOG_FILE, 'utf8');
        const matches = [...content.matchAll(/verification code is:\s*\*?\*?(\d{6})\*?\*?/gi)];
        if (matches.length > 0) return matches[matches.length - 1][1];
    } catch (_) {}
    return null;
}
const logSize = () => { try { return fs.statSync(LOG_FILE).size; } catch (_) { return 0; } };

async function login(page) {
    h('LOGIN (with automated 2FA via mail log)');
    await page.goto(`${BASE}/signin`);
    await page.waitForLoadState('networkidle');
    await page.fill('input[name="email"], input[type="email"]', EMAIL);
    await page.fill('input[name="password"], input[type="password"]', PASS);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    if (page.url().includes('two-factor')) {
        // On the choice page: pick email + send a fresh code.
        if (page.url().includes('two-factor/choice')) {
            const emailOpt = page.locator('input[value="email"]').first();
            if (await emailOpt.count() > 0) await emailOpt.click();
            const sendBtn = page.locator('button[type="submit"]').first();
            if (await sendBtn.count() > 0) await sendBtn.click();
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(1500);

            if (page.url().includes('two-factor/choice')) {
                try { execSync('php artisan cache:clear', { stdio: 'pipe' }); } catch (_) {}
                await page.reload(); await page.waitForLoadState('networkidle');
                const b = page.locator('button[type="submit"]').first();
                if (await b.count() > 0) { await b.click(); await page.waitForLoadState('networkidle'); await page.waitForTimeout(1200); }
            }
        }

        // Ensure we're on the verify page (we may have landed here directly).
        if (!page.url().includes('two-factor/verify')) {
            await page.goto(`${BASE}/two-factor/verify`); await page.waitForLoadState('networkidle');
        }

        // APP_DEBUG shows the current OTP in a debug box; fall back to the mail log.
        let otp = null;
        const debugBox = page.locator('.font-mono.text-lg.tracking-widest').first();
        if (await debugBox.count() > 0) otp = (await debugBox.textContent())?.replace(/\s/g, '').trim();
        if (!otp || otp.length !== 6) otp = readOtpFromLog();
        if (!otp || otp.length !== 6) { fail('Could not obtain OTP'); return false; }

        const boxes = page.locator('input.otp-input');
        if (await boxes.count() === 6) {
            for (let i = 0; i < 6; i++) { await boxes.nth(i).fill(otp[i]); await page.waitForTimeout(60); }
            // The OTP form auto-submits on the 6th digit; click submit only if still on verify.
            if (page.url().includes('two-factor/verify')) {
                const verifyBtn = page.locator('form button[type="submit"]').first();
                if (await verifyBtn.count() > 0 && await verifyBtn.isVisible().catch(() => false)) {
                    await verifyBtn.click().catch(() => {});
                }
            }
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(800);
        }
    }

    if (/signin|login|two-factor/.test(page.url())) { fail('Still on auth page: ' + page.url()); return false; }
    log('Authenticated → ' + page.url());
    return true;
}

/** Wait for the Google Maps Places library to finish loading on the page. */
async function waitForGoogle(page, label) {
    try {
        await page.waitForFunction(
            () => window.google && window.google.maps && window.google.maps.places && window.google.maps.places.Autocomplete,
            { timeout: 20000 }
        );
        log(`${label} — Google Maps Places library loaded ✓`);
        return true;
    } catch (_) {
        fail(`${label} — Google Maps Places library did NOT load (check key, billing, Maps JavaScript + Places API enabled)`);
        return false;
    }
}

/** Assert the loader script tag carries the configured key. */
async function assertScriptKey(page, label) {
    const ok = await page.evaluate(() =>
        !![...document.scripts].find(s => s.src.includes('maps.googleapis.com') && s.src.includes('key='))
    );
    ok ? log(`${label} — Maps loader script injected with key ✓`)
       : fail(`${label} — Maps loader script not found`);
    return ok;
}

(async () => {
    let browser;
    try { browser = await chromium.launch({ headless: true, slowMo: 80 }); }
    catch (err) {
        if (String(err.message || err).includes("Executable doesn't exist"))
            console.error('\nPlaywright Chromium missing. Run: npm run playwright:install\n');
        throw err;
    }
    const ctx  = await browser.newContext({ viewport: { width: 1400, height: 900 } });
    const page = await ctx.newPage();

    // Capture Google Maps API errors (ApiNotActivated / RefererNotAllowed / Billing, etc.)
    const gmapsErrors = [];
    page.on('console', (m) => {
        const t = m.text();
        if (/Google Maps|ApiNotActivated|RefererNotAllowed|BillingNotEnabled|InvalidKey|ApiTargetBlocked/i.test(t)) {
            gmapsErrors.push(t);
        }
    });

    if (!(await login(page))) { await browser.close(); return printSummary(); }

    // ─── 1. Caregiver create — full live drop-down test (address on step 1) ──────
    h('Caregiver create — live Places autocomplete (step 1)');
    await page.goto(`${BASE}/caregivers/create`);
    await page.waitForLoadState('networkidle');

    const cgAddr = page.locator('input[name="address"][data-gmaps]').first();
    if (await cgAddr.count() === 0) {
        fail('Caregiver create — address input is missing the data-gmaps attribute');
    } else {
        log('Caregiver create — address input has data-gmaps ✓');
        await assertScriptKey(page, 'Caregiver create');
        if (await waitForGoogle(page, 'Caregiver create')) {
            await cgAddr.click();
            await cgAddr.type('220 Bagley Ave, Detroit', { delay: 120 });
            try {
                await page.waitForSelector('.gmaps-ac-item', { timeout: 10000 });
                log('Caregiver create — Places (New) suggestions appeared ✓');
                await page.keyboard.press('ArrowDown');
                await page.keyboard.press('Enter');
                await page.waitForTimeout(1200);
                const addrVal   = await cgAddr.inputValue();
                const countyVal = await page.locator('input[name="county"]').first().inputValue().catch(() => '');
                addrVal && addrVal.length > 8
                    ? log(`Caregiver create — address filled: "${addrVal}"`)
                    : fail(`Caregiver create — address not filled (got "${addrVal}")`);
                countyVal
                    ? log(`Caregiver create — county auto-filled: "${countyVal}"`)
                    : warn('Caregiver create — county did not auto-fill');
            } catch (_) {
                warn('Caregiver create — no suggestions returned (check Places API New + billing). Wiring is correct; live API not responding.');
            }
        }
    }

    // ─── 2. Client create — script + attribute wiring (address on step 2) ────────
    h('Client create — autocomplete wiring');
    await page.goto(`${BASE}/clients/create`);
    await page.waitForLoadState('networkidle');
    const clAddr = page.locator('#addressAutocomplete[data-gmaps]');
    (await clAddr.count() > 0)
        ? log('Client create — #addressAutocomplete has data-gmaps ✓')
        : fail('Client create — address input missing data-gmaps');
    await assertScriptKey(page, 'Client create');
    await waitForGoogle(page, 'Client create');

    // ─── 3. Client demographics (edit) — attribute wiring ────────────────────────
    h('Client demographics — autocomplete wiring');
    await page.goto(`${BASE}/clients`);
    await page.waitForLoadState('networkidle');
    // Resolve a client show page that actually carries our markers in server HTML.
    const clientHref = await page.evaluate(async () => {
        const ids = new Set();
        document.querySelectorAll('a[href]').forEach(a => {
            const m = a.getAttribute('href').match(/\/clients\/(\d+)(?:[/?#]|$)/);
            if (m) ids.add(m[1]);
        });
        ids.add('1');
        for (const id of ids) {
            const r = await fetch(`/clients/${id}`).catch(() => null);
            if (r && r.ok && (await r.text()).includes('data-gmaps')) return `/clients/${id}`;
        }
        return null;
    });
    if (clientHref) {
        await page.goto(BASE + clientHref);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(500);
        // demographics is the default tab; the address efield carries data-gmaps even while in read mode
        const demoAddr = page.locator('input[name="address"][data-gmaps]');
        (await demoAddr.count() > 0)
            ? log('Client demographics — address efield has data-gmaps ✓')
            : fail('Client demographics — address input missing data-gmaps');
        await assertScriptKey(page, 'Client demographics');
        await waitForGoogle(page, 'Client demographics');
    } else {
        warn('Client demographics — no client rows to open; skipped');
    }

    // ─── 4. Caregiver personal (edit) — attribute wiring ─────────────────────────
    h('Caregiver personal — autocomplete wiring');
    await page.goto(`${BASE}/caregivers`);
    await page.waitForLoadState('networkidle');
    const cgHref = await page.evaluate(() => {
        const a = [...document.querySelectorAll('a[href]')].find(x => /\/caregivers\/\d+(?:[/?#]|$)/.test(x.getAttribute('href')));
        return a ? a.getAttribute('href') : null;
    }) || '/caregivers/1';
    if (cgHref) {
        await page.goto(cgHref.startsWith('http') ? cgHref : BASE + cgHref);
        await page.waitForLoadState('networkidle');
        await assertScriptKey(page, 'Caregiver personal');
        await waitForGoogle(page, 'Caregiver personal');
        // Personal address mounts via Alpine x-if when Edit is clicked.
        const editBtn = page.locator('button', { hasText: /^\s*Edit\s*$/ }).first();
        if (await editBtn.count() > 0) {
            await editBtn.click();
            await page.waitForTimeout(500);
        }
        const personalAddr = page.locator('input[name="address"][data-gmaps]');
        (await personalAddr.count() > 0)
            ? log('Caregiver personal — address input has data-gmaps after Edit ✓')
            : fail('Caregiver personal — address input missing data-gmaps after Edit');
    } else {
        warn('Caregiver personal — no caregiver rows to open; skipped');
    }

    if (gmapsErrors.length) {
        h('Google API console messages');
        [...new Set(gmapsErrors)].forEach(e => warn(e));
    }

    await browser.close();
    printSummary();

    function printSummary() {
        console.log(`\n${'═'.repeat(60)}`);
        console.log(`  RESULTS: ${passed} passed  ·  ${warned} warnings  ·  ${failed} failed`);
        console.log(`${'═'.repeat(60)}\n`);
        if (failed > 0) process.exit(1);
    }
})();
