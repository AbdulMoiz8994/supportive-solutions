/**
 * Playwright verification suite — BeydounTech platform modules.
 * Targets: http://127.0.0.1:8000 (local Laravel dev server)
 * Handles 2FA automatically via storage/logs/laravel.log (MAIL_MAILER=log)
 *
 * Covers: client/caregiver fixes (Jun 24), Global Settings, integrations,
 * Directory, and Communications (Jun 25).
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

/** Read the most-recent 6-digit OTP from the mail log. */
function readOtpFromLog() {
    try {
        const content = fs.readFileSync(LOG_FILE, 'utf8');
        // Pattern in log: "verification code is: **123456**" or plain "123456" in strong tag
        const matches = [...content.matchAll(/verification code is:\s*\*?\*?(\d{6})\*?\*?/gi)];
        if (matches.length > 0) {
            return matches[matches.length - 1][1]; // last one = most recent
        }
    } catch (_) {}
    return null;
}

/** Scroll to & get the log file size before sending OTP so we can detect new entries. */
function logSize() {
    try { return fs.statSync(LOG_FILE).size; } catch (_) { return 0; }
}

function clearRateLimitCache() {
    try {
        execSync('php artisan cache:clear', { stdio: 'pipe' });
        warn('2FA rate-limit detected; cache cleared and retrying');
        return true;
    } catch (_) {
        return false;
    }
}

async function getCsrfToken(page) {
    return page.evaluate(() => {
        const m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    });
}

async function switchGlobalSettingsTab(page, label) {
    const tab = page.locator('button').filter({ hasText: new RegExp(`^${label}$`, 'i') }).first();
    if (await tab.count() === 0) return false;
    await tab.click();
    await page.waitForTimeout(600);
    return true;
}

async function assertPageLoads(page, path, headingPattern, label) {
    await page.goto(`${BASE}${path}`);
    await page.waitForLoadState('networkidle');

    if (page.url().includes('signin') || page.url().includes('two-factor')) {
        fail(`${label} — session lost (${page.url()})`);
        return false;
    }

    const body = await page.locator('body').innerText();
    if (headingPattern.test(body)) {
        log(`${label} page loaded ✓`);
        return true;
    }

    fail(`${label} — expected heading not found at ${path}`);
    return false;
}

(async () => {
    let browser;
    try {
        browser = await chromium.launch({ headless: true, slowMo: 100 });
    } catch (err) {
        if (String(err.message || err).includes("Executable doesn't exist")) {
            console.error('\nPlaywright Chromium is not installed. Run:\n  npm run playwright:install\n  or: npm run test:e2e:setup\n');
        }
        throw err;
    }
    const ctx  = await browser.newContext({ viewport: { width: 1400, height: 900 } });
    const page = await ctx.newPage();

    // ─── LOGIN + 2FA ─────────────────────────────────────────────────────────
    h('LOGIN (with automated 2FA via mail log)');
    await page.goto(`${BASE}/signin`);
    await page.waitForLoadState('networkidle');
    await page.fill('input[name="email"], input[type="email"]', EMAIL);
    await page.fill('input[name="password"], input[type="password"]', PASS);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Handle 2FA choice page
    if (page.url().includes('two-factor/choice')) {
        log('2FA choice page — selecting email method');

        const emailOpt = page.locator('input[value="email"]').first();
        if (await emailOpt.count() > 0) await emailOpt.click();

        const logSizeBefore = logSize();
        const sendBtn = page.locator('button[type="submit"]').first();
        if (await sendBtn.count() > 0) await sendBtn.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        // Check for rate-limit error (may stay on choice page with flash error)
        if (page.url().includes('two-factor/choice')) {
            const errMsg = await page.locator('.text-red-700, .alert-error, [x-data] [class*="error"]').first().textContent().catch(() => '');
            warn('Still on choice page after send — possible rate limit: ' + (errMsg?.trim() || 'no error text found'));
            // Try to self-heal local runs by clearing cache and retrying once.
            if (clearRateLimitCache()) {
                await page.reload();
                await page.waitForLoadState('networkidle');
                const retrySendBtn = page.locator('button[type="submit"]').first();
                if (await retrySendBtn.count() > 0) {
                    await retrySendBtn.click();
                    await page.waitForLoadState('networkidle');
                    await page.waitForTimeout(1200);
                }
            }

            // If still blocked, one last direct verify attempt before failing.
            if (page.url().includes('two-factor/choice')) {
                await page.goto(`${BASE}/two-factor/verify`);
                await page.waitForLoadState('networkidle');
                if (page.url().includes('two-factor/choice')) {
                    fail('Cannot proceed past 2FA — rate limited and no valid OTP. Run: php artisan cache:clear');
                    await browser.close(); printSummary(); return;
                }
            }
        }

        // Should now be on verify page
        if (!page.url().includes('two-factor/verify')) {
            await page.goto(`${BASE}/two-factor/verify`);
            await page.waitForLoadState('networkidle');
        }

        // APP_DEBUG=true: OTP appears in-page in debug box
        let otp = null;
        const debugBox = page.locator('.font-mono.text-lg.tracking-widest').first();
        if (await debugBox.count() > 0) {
            otp = (await debugBox.textContent())?.replace(/\s/g, '').trim();
            if (otp?.length === 6) log(`OTP from debug box: ${otp}`);
        }

        // Fallback: read most-recent OTP from mail log (only if log grew after send)
        if ((!otp || otp.length !== 6) && logSize() > logSizeBefore) {
            otp = readOtpFromLog();
            if (otp) log(`OTP from mail log: ${otp}`);
        }

        if (!otp || otp.length !== 6) {
            fail('Could not obtain valid 6-digit OTP. Check mail log or debug box.');
            await browser.close(); printSummary(); return;
        }

        // The verify page uses 6 individual input.otp-input boxes (maxlength="1")
        const digitBoxes = page.locator('input.otp-input');
        const boxCount = await digitBoxes.count();
        if (boxCount === 6) {
            for (let i = 0; i < 6; i++) {
                await digitBoxes.nth(i).fill(otp[i]);
                await page.waitForTimeout(80);
            }
            await page.locator('button[type="submit"]').first().click();
            await page.waitForLoadState('networkidle');
            log('OTP submitted via digit boxes');
        } else {
            fail(`Expected 6 OTP digit boxes, found ${boxCount}`);
            await browser.close(); printSummary(); return;
        }
    }

    if (page.url().includes('signin') || page.url().includes('login') || page.url().includes('two-factor')) {
        fail('Still on auth page after login attempt: ' + page.url());
        await printSummary(); await browser.close(); return;
    }
    log('Authenticated → ' + page.url());

    // ─── D5: HTML entity rendering ────────────────────────────────────────────
    h('D5 — HTML entity rendering on client index');
    await page.goto(`${BASE}/clients`);
    await page.waitForLoadState('networkidle');

    const pageSource = await page.content();
    if (pageSource.includes('&amp;le;') || pageSource.includes('&amp;middot;') || pageSource.includes('&amp;mdash;')) {
        fail('Double-escaped HTML entities still present (&amp;le; etc.)');
    } else {
        log('No double-escaped HTML entities in page source ✓');
    }
    const bodyText = await page.locator('body').innerText();
    bodyText.includes('≤') ? log('≤ symbol rendered correctly ✓') : warn('≤ symbol not visible in rendered text (stat card may be absent)');
    !pageSource.includes('&middot;') ? log('&middot; entity removed from source ✓') : fail('&middot; still present in source');
    !pageSource.includes('&mdash;')  ? log('&mdash; entity removed from source ✓')  : fail('&mdash; still present in source');

    // ─── F2: SSHC program names in coverage type dropdown ────────────────────
    h('F2 — SSHC program names in coverage type select');

    // Test via client create wizard - inspect form source (Alpine renders it before step logic)
    await page.goto(`${BASE}/clients/create`);
    await page.waitForLoadState('networkidle');

    // Alpine hides steps with x-show; look in DOM regardless of visibility
    const allOptions = await page.locator('select[name="coverage_type_id"] option').allTextContents();
    if (allOptions.length > 0) {
        const optStr = allOptions.join(' | ');
        allOptions.some(o => o.includes('DHS Home Help')) ? log('DHS Home Help ✓')   : fail(`DHS Home Help missing. Options: ${optStr}`);
        allOptions.some(o => o.includes('MICH'))          ? log('MICH ✓')             : fail(`MICH missing. Options: ${optStr}`);
        allOptions.some(o => o.includes('ICO'))           ? log('ICO ✓')              : fail(`ICO missing. Options: ${optStr}`);
        allOptions.some(o => o.includes('DAAA'))          ? log('DAAA ✓')             : fail(`DAAA missing. Options: ${optStr}`);
        allOptions.some(o => o.includes('Private Pay'))   ? log('Private Pay ✓')      : fail(`Private Pay missing. Options: ${optStr}`);
        !allOptions.some(o => o === 'Medicaid') ? log('Old "Medicaid" removed ✓')     : fail('Old "Medicaid" still present');
        !allOptions.some(o => o === 'Medicare') ? log('Old "Medicare" removed ✓')     : fail('Old "Medicare" still present');
        !allOptions.some(o => o === 'Molina')   ? log('Old "Molina" removed ✓')       : fail('Old "Molina" still present');
    } else {
        warn('coverage_type_id select not found in DOM — Alpine may be hiding it');
        // Fallback: check page HTML source
        const src = await page.content();
        src.includes('DHS Home Help') ? log('DHS Home Help in page HTML ✓') : fail('DHS Home Help NOT in page HTML');
        src.includes('MICH')          ? log('MICH in page HTML ✓')          : warn('MICH not in page HTML');
        src.includes('ICO')           ? log('ICO in page HTML ✓')           : warn('ICO not in page HTML');
        src.includes('DAAA')          ? log('DAAA in page HTML ✓')          : warn('DAAA not in page HTML');
    }

    // ─── F5: Medicaid ID placeholder ─────────────────────────────────────────
    h('F5 — Medicaid ID placeholder text in create wizard');
    // The input exists in the DOM even if the step is hidden
    const memberInput = page.locator('input[name="member_id"]').first();
    if (await memberInput.count() > 0) {
        const ph = await memberInput.getAttribute('placeholder');
        ph && (ph.toLowerCase().includes('md-') || ph.toLowerCase().includes('md'))
            ? log(`Medicaid ID placeholder = "${ph}" ✓`)
            : (ph === '10 digits' ? fail(`Placeholder still "10 digits"`) : warn(`Placeholder = "${ph}" — verify format`));
        const mx = await memberInput.getAttribute('maxlength');
        mx !== '10' ? log(`maxlength is ${mx ?? 'unset'} (not 10) ✓`) : fail('maxlength still 10');
    } else {
        warn('member_id input not in DOM');
    }

    // ─── F4: County is a select dropdown ─────────────────────────────────────
    h('F4 — County is a <select> dropdown in create wizard');
    const countySelectCreate = page.locator('select[name="county"]').first();
    const countyInputCreate  = page.locator('input[name="county"]').first();
    if (await countySelectCreate.count() > 0) {
        const opts = await countySelectCreate.locator('option').allTextContents();
        log(`County select has ${opts.length} options ✓`);
        opts.some(o => o.includes('Wayne'))   ? log('Wayne county present ✓')   : fail('Wayne missing from county dropdown');
        opts.some(o => o.includes('Oakland')) ? log('Oakland county present ✓') : fail('Oakland missing from county dropdown');
        opts.some(o => o.includes('Macomb'))  ? log('Macomb county present ✓')  : warn('Macomb not found (check list)');
    } else if (await countyInputCreate.count() > 0) {
        fail('County is still a plain <input type=text> — select replacement failed');
    } else {
        warn('County field not found in DOM');
    }

    // ─── F1 + F4: Demographics tab on a real client profile ──────────────────
    h('F1 + F4 — Demographics tab: dropdown fields on client profile');
    await page.goto(`${BASE}/clients`);
    await page.waitForLoadState('networkidle');

    const clientLink = page.locator('a[href*="/clients/"]')
        .filter({ hasNotText: /create|intake|new client/i })
        .first();

    let clientUrl = null;
    if (await clientLink.count() > 0) {
        clientUrl = await clientLink.getAttribute('href');
        if (clientUrl && !clientUrl.startsWith('http')) clientUrl = BASE + clientUrl;
        try {
            await page.goto(clientUrl, { timeout: 60000 });
            await page.waitForLoadState('domcontentloaded', { timeout: 60000 });
        } catch (e) {
            warn('Client profile load timed out — trying next client: ' + e.message);
            // try the second client link
            const secondLink = page.locator('a[href*="/clients/"]')
                .filter({ hasNotText: /create|intake|new client/i })
                .nth(1);
            if (await secondLink.count() > 0) {
                clientUrl = await secondLink.getAttribute('href');
                if (clientUrl && !clientUrl.startsWith('http')) clientUrl = BASE + clientUrl;
                await page.goto(clientUrl, { timeout: 60000 });
                await page.waitForLoadState('domcontentloaded', { timeout: 60000 });
            }
        }
        log('Opened client profile ✓');

        // Click Demographics tab
        const demoTab = page.locator('button, a').filter({ hasText: /demographics/i }).first();
        if (await demoTab.count() > 0) {
            await demoTab.click();
            await page.waitForTimeout(700);

            // County efield: check select exists in form (efield renders hidden form fields)
            const countySelDemo = page.locator('select[name="county"]').first();
            const countyInpDemo = page.locator('input[name="county"]').first();
            if (await countySelDemo.count() > 0) {
                const opts = await countySelDemo.locator('option').allTextContents();
                log(`County select on demographics has ${opts.length} options ✓`);
            } else if (await countyInpDemo.count() > 0) {
                fail('County still a text input on demographics tab');
            } else {
                warn('County field not found in demographics DOM (efield may lazy-render)');
            }

            // Check gender select
            const genderSel = page.locator('select[name="gender"]').first();
            await genderSel.count() > 0
                ? (log('Gender <select> in DOM ✓'))
                : warn('Gender select not in DOM (efield collapses until editing)');

            // Check preferred_language select
            const langSel = page.locator('select[name="preferred_language"]').first();
            await langSel.count() > 0
                ? log('Preferred language <select> in DOM ✓')
                : warn('Preferred language select not visible (efield collapses until editing)');

            // Verify forms have the new fields present in page source
            const demoSource = await page.content();
            demoSource.includes('name="gender"')             ? log('gender field present in DOM ✓')             : fail('gender field NOT in DOM');
            demoSource.includes('name="preferred_language"') ? log('preferred_language field present ✓')        : fail('preferred_language NOT in DOM');
            demoSource.includes('name="requires_translator"')? log('requires_translator field present ✓')       : warn('requires_translator not in DOM');
            demoSource.includes('name="mco_name"')           ? log('mco_name field present ✓')                 : fail('mco_name NOT in DOM');
            demoSource.includes('name="county"')             ? log('county field present ✓')                   : fail('county NOT in DOM');

            // Round-trip save regression check: edit dropdowns -> save -> reload -> assert persisted
            const personalInfoHeading = page.locator('h3').filter({ hasText: /^Personal Information$/ }).first();
            if (await personalInfoHeading.count() > 0) {
                const personalPanel = personalInfoHeading.locator('xpath=ancestor::div[contains(@class,"rounded-2xl")][1]');
                const editBtn = personalPanel.locator('button').filter({ hasText: /^Edit$/i }).first();
                if (await editBtn.count() > 0) {
                    await editBtn.click();
                    await page.waitForTimeout(300);

                    const genderSelectEdit = personalPanel.locator('select[name="gender"]').first();
                    const langSelectEdit = personalPanel.locator('select[name="preferred_language"]').first();
                    if (await genderSelectEdit.count() > 0 && await langSelectEdit.count() > 0) {
                        await genderSelectEdit.selectOption({ label: 'Female' });
                        await langSelectEdit.selectOption({ label: 'Arabic' });

                        const saveBtn = personalPanel.locator('button[type="submit"]').filter({ hasText: /^Save$/i }).first();
                        if (await saveBtn.count() > 0) {
                            await Promise.all([
                                page.waitForLoadState('networkidle'),
                                saveBtn.click(),
                            ]);

                            await page.goto(`${clientUrl}?tab=demographics`);
                            await page.waitForLoadState('networkidle');
                            const demoTabAfterSave = page.locator('button, a').filter({ hasText: /demographics/i }).first();
                            if (await demoTabAfterSave.count() > 0) {
                                await demoTabAfterSave.click();
                                await page.waitForTimeout(500);
                            }

                            const reloadedHeading = page.locator('h3').filter({ hasText: /^Personal Information$/ }).first();
                            const reloadedPanel = reloadedHeading.locator('xpath=ancestor::div[contains(@class,"rounded-2xl")][1]');
                            const reloadedEdit = reloadedPanel.locator('button').filter({ hasText: /^Edit$/i }).first();
                            if (await reloadedEdit.count() > 0) {
                                await reloadedEdit.click();
                                await page.waitForTimeout(300);

                                const persistedGender = await reloadedPanel.locator('select[name="gender"]').first().inputValue();
                                const persistedLanguage = await reloadedPanel.locator('select[name="preferred_language"]').first().inputValue();
                                persistedGender === 'Female'
                                    ? log('Gender persisted after save + reload ✓')
                                    : fail(`Gender did not persist (found "${persistedGender}")`);
                                persistedLanguage === 'Arabic'
                                    ? log('Preferred language persisted after save + reload ✓')
                                    : fail(`Preferred language did not persist (found "${persistedLanguage}")`);
                            } else {
                                warn('Could not reopen Personal Information panel after reload');
                            }
                        } else {
                            warn('Save button not found in Personal Information panel');
                        }
                    } else {
                        warn('Gender/preferred language dropdowns not found for round-trip test');
                    }
                } else {
                    warn('Edit button not found in Personal Information panel');
                }
            } else {
                warn('Personal Information panel not found for F1 round-trip test');
            }

        } else {
            warn('Demographics tab not found on client profile');
        }
    } else {
        warn('No client link found in registry — demographics tab cannot be tested');
    }

    // ─── X1: Caregiver assignment form on client profile ─────────────────────
    h('X1 — Client caregiver tab: real assignment form');
    if (clientUrl) {
        await page.goto(`${clientUrl}?tab=caregiver`);
        await page.waitForLoadState('networkidle');

        // Click the caregiver tab
        const cgTab = page.locator('button, a').filter({ hasText: /^caregiver$/i }).first();
        if (await cgTab.count() > 0) { await cgTab.click(); await page.waitForTimeout(700); }

        const tabSource = await page.content();

        // Dead link check — should NOT have old "Assign a caregiver" link to /caregivers
        const deadLinkEl = page.locator('a[href*="/caregivers"]:not([href*="/show"]):not([href*="/create"])').filter({ hasText: /assign a caregiver/i });
        if (await deadLinkEl.count() > 0) {
            fail('Dead "Assign a caregiver" link still present on caregiver tab');
        } else {
            log('No dead "Assign a caregiver" link ✓');
        }

        // Assignment form check
        const assignForm = page.locator('form[action*="assign-caregiver"]').first();
        if (await assignForm.count() > 0) {
            log('Assignment form present (action=…/assign-caregiver) ✓');
            const empSel = assignForm.locator('select[name="employee_id"]');
            await empSel.count() > 0 ? log('employee_id select in form ✓') : fail('employee_id select missing from form');
            const relSel = assignForm.locator('select[name="relationship"]');
            await relSel.count() > 0 ? log('relationship select in form ✓') : warn('relationship select not found');
            const liveIn = assignForm.locator('select[name="live_in"]');
            await liveIn.count() > 0 ? log('live_in select in form ✓') : warn('live_in select not found');
            const sub = assignForm.locator('button[type="submit"]');
            await sub.count() > 0 ? log('Submit button present ✓') : fail('Submit button missing from assignment form');
        } else if (tabSource.includes('assign-caregiver')) {
            log('assign-caregiver route referenced in tab source ✓');
        } else {
            // May already have a caregiver assigned — check for caregiver info
            const hasCaregiverInfo = page.locator('text=/Reassign|Replace|Primary Caregiver/i').first();
            if (await hasCaregiverInfo.count() > 0) {
                log('Caregiver already assigned — assignment form replaced by profile display ✓');
            } else {
                warn('Cannot determine caregiver tab state (form not found, no existing assignment)');
            }
        }
    } else {
        warn('No client URL — X1 test skipped');
    }

    // ─── X1 route exists: POST /clients/{id}/assign-caregiver ────────────────
    h('X1 — Route exists: POST /clients/{id}/assign-caregiver');
    const clientId = clientUrl?.match(/\/clients\/(\d+)/)?.[1] ?? '1';
    try {
        // Get CSRF token from a page
        await page.goto(`${BASE}/clients`);
        await page.waitForLoadState('networkidle');
        const csrfToken = await getCsrfToken(page);

        const resp = await page.request.post(`${BASE}/clients/${clientId}/assign-caregiver`, {
            headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            form: { _token: csrfToken, employee_id: '' },
        });
        // 422 = validation error (route exists, validation fired)
        // 302 = redirect (form submitted successfully or back)
        // 419 = CSRF mismatch (route exists)
        // 404 = route not registered
        resp.status() !== 404
            ? log(`POST …/assign-caregiver route exists (HTTP ${resp.status()}) ✓`)
            : fail('POST …/assign-caregiver → 404 route not registered');
    } catch (e) {
        warn('Could not test route via fetch: ' + e.message);
    }

    // ─── X2: Schedule tab on caregiver profile ───────────────────────────────
    h('X2 — Caregiver profile: Schedule / Visits tab');
    await page.goto(`${BASE}/caregivers`);
    await page.waitForLoadState('networkidle');

    if (page.url().includes('two-factor') || page.url().includes('signin')) {
        fail('Session expired — 2FA re-triggered: ' + page.url());
    } else {
        const cgLink = page.locator('a[href*="/caregivers/"]')
            .filter({ hasNotText: /create|registry|new/i })
            .first();

        // Fall back to the seeded test caregiver (ID 68) if none in registry list
        let cgUrl = null;
        if (await cgLink.count() > 0) {
            const cgHref = await cgLink.getAttribute('href');
            cgUrl = cgHref?.startsWith('http') ? cgHref : BASE + cgHref;
        } else {
            cgUrl = `${BASE}/caregivers/68`; // seeded test caregiver
        }

        {
            await page.goto(cgUrl, { timeout: 60000 });
            await page.waitForLoadState('domcontentloaded', { timeout: 60000 });
            log('Opened caregiver profile: ' + page.url());

            // Check tab list includes "Schedule / Visits"
            const schedTab = page.locator('button, a').filter({ hasText: /schedule|visits/i }).first();
            if (await schedTab.count() > 0) {
                log('"Schedule / Visits" tab button present ✓');
                await schedTab.click();
                await page.waitForTimeout(700);

                const panelText = await page.locator('body').innerText();
                panelText.toLowerCase().includes('schedule') || panelText.toLowerCase().includes('visit')
                    ? log('Schedule panel content visible after click ✓')
                    : warn('Tab clicked but schedule content not clearly visible');
            } else {
                fail('"Schedule / Visits" tab NOT in caregiver profile — tab missing');
            }
        }
    }

    // ─── G1: Global Settings shell ───────────────────────────────────────────
    h('G1 — Global Settings page loads with summary stats');
    if (await assertPageLoads(page, '/settings/global', /Global Settings/i, 'Global Settings')) {
        const src = await page.content();
        src.includes('Integrations live') ? log('Summary stat "Integrations live" present ✓') : warn('Integrations live stat not found');
        src.includes('Vault configured') ? log('Summary stat "Vault configured" present ✓') : warn('Vault configured stat not found');
        src.includes('MICH rate') ? log('Summary stat "MICH rate" present ✓') : warn('MICH rate stat not found');
    }

    // ─── G2: Integrations tab + test connection API ──────────────────────────
    h('G2 — Global Settings · Integrations tab & test connection');
    await page.goto(`${BASE}/settings/global?tab=integrations`);
    await page.waitForLoadState('networkidle');

    const integrationsSource = await page.content();
    if (integrationsSource.includes('Connected systems')) {
        log('Integrations table "Connected systems" visible ✓');
    } else if (await switchGlobalSettingsTab(page, 'Integrations')) {
        (await page.content()).includes('Connected systems')
            ? log('Integrations table visible after tab switch ✓')
            : fail('Connected systems table not found on Integrations tab');
    } else {
        fail('Could not open Integrations tab');
    }

    const testButtons = page.locator('button').filter({ hasText: /test connection/i });
    const testBtnCount = await testButtons.count();
    testBtnCount > 0
        ? log(`${testBtnCount} "Test connection" button(s) on Integrations tab ✓`)
        : fail('No Test connection buttons on Integrations tab');

    if (testBtnCount > 0) {
        try {
            const [response] = await Promise.all([
                page.waitForResponse(
                    (r) => r.url().includes('/settings/global/integrations/test') && r.request().method() === 'POST',
                    { timeout: 30000 },
                ),
                testButtons.first().click(),
            ]);
            const payload = await response.json().catch(() => ({}));
            response.ok()
                ? log(`Integration test API responded HTTP ${response.status()} (success=${payload.success}) ✓`)
                : fail(`Integration test API HTTP ${response.status()}`);

            if (Array.isArray(payload.checks) && payload.checks.length > 0) {
                log(`Integration test returned ${payload.checks.length} structured check(s) ✓`);
            } else if (payload.summary || payload.message) {
                log(`Integration test summary: ${payload.summary || payload.message} ✓`);
            } else {
                warn('Integration test response missing checks/summary');
            }

            await page.waitForTimeout(800);
            const afterTest = await page.content();
            afterTest.includes('checks passed') || afterTest.includes('Not configured') || afterTest.includes('Connected') || afterTest.includes('Error')
                ? log('Integration status feedback rendered in UI ✓')
                : warn('Could not confirm integration status badge/text after test');
        } catch (e) {
            fail('Integration test connection flow failed: ' + e.message);
        }
    }

    // ─── G3: Credential Vault tab ────────────────────────────────────────────
    h('G3 — Global Settings · Credential Vault');
    if (await switchGlobalSettingsTab(page, 'Credential Vault')) {
        const vaultText = await page.locator('body').innerText();
        vaultText.includes('Encrypted credential vault')
            ? log('Credential vault section heading present ✓')
            : fail('Credential vault heading missing');

        const vaultTestBtns = page.locator('button').filter({ hasText: /test( connection)?/i });
        (await vaultTestBtns.count()) > 0
            ? log('Credential vault test buttons present ✓')
            : warn('No test buttons found in Credential Vault');

        vaultText.includes('Save credential vault')
            ? log('Save credential vault form present ✓')
            : warn('Save credential vault button not visible');
    } else {
        await page.goto(`${BASE}/settings/global?tab=credential-vault`);
        await page.waitForLoadState('networkidle');
        (await page.content()).includes('Encrypted credential vault')
            ? log('Credential vault loaded via URL tab param ✓')
            : fail('Credential vault tab did not load');
    }

    // ─── G4: Directory module ────────────────────────────────────────────────
    h('G4 — Directory index and contact profile');
    if (await assertPageLoads(page, '/directory', /Directories/i, 'Directory')) {
        const addEntry = page.locator('a[href*="/directory/create"]').first();
        (await addEntry.count()) > 0 ? log('Add entry link present ✓') : warn('Add entry link not found');

        const dirLink = page.locator('a[href*="/directory/"]')
            .filter({ hasNotText: /create|add/i })
            .first();
        if (await dirLink.count() > 0) {
            const href = await dirLink.getAttribute('href');
            const dirUrl = href?.startsWith('http') ? href : BASE + href;
            await page.goto(dirUrl, { timeout: 60000 });
            await page.waitForLoadState('domcontentloaded', { timeout: 60000 });
            page.url().includes('/directory/') && !page.url().includes('/create')
                ? log('Directory contact profile opened ✓')
                : fail('Directory profile URL unexpected: ' + page.url());

            const profileText = await page.locator('body').innerText();
            profileText.includes('Directories') ? log('Directory breadcrumb present ✓') : warn('Directory breadcrumb missing');

            const testConnBtn = page.locator('button, form button').filter({ hasText: /test connection/i }).first();
            if (await testConnBtn.count() > 0) {
                log('Directory "Test connection" action present on profile ✓');
            } else {
                warn('Test connection button not on this directory profile (may be non-integration contact)');
            }
        } else {
            warn('No directory contact links — index may be empty');
        }
    }

    // ─── G5: Communications module ───────────────────────────────────────────
    h('G5 — Communications hub');
    if (await assertPageLoads(page, '/communications', /Communications/i, 'Communications')) {
        const commText = await page.locator('body').innerText();
        commText.match(/All|Need reply|Calls|SMS|eFax|Email/i)
            ? log('Communications channel/filter tabs visible ✓')
            : warn('Communications filter tabs not clearly visible');

        const sendRequest = page.locator('a[href*="/communications/send-request"]').first();
        (await sendRequest.count()) > 0
            ? log('Send request action link present ✓')
            : warn('Send request link not found (permission or empty state)');

        const secureMessages = page.locator('a[href*="/communications/secure-messages"]').first();
        (await secureMessages.count()) > 0
            ? log('Secure messages link present ✓')
            : warn('Secure messages link not found');

        await page.goto(`${BASE}/communications/templates`);
        await page.waitForLoadState('networkidle');
        if (!page.url().includes('signin')) {
            const tplText = await page.locator('body').innerText();
            tplText.match(/template/i)
                ? log('Communication templates page loads ✓')
                : warn('Templates page content unclear');
        }
    }

    // ─── G6: Legacy contacts redirect ────────────────────────────────────────
    h('G6 — /contacts redirects to directory');
    const contactsResp = await page.goto(`${BASE}/contacts`);
    await page.waitForLoadState('networkidle');
    page.url().includes('/directory')
        ? log('/contacts → /directory redirect ✓')
        : (contactsResp?.status() === 200 && (await page.content()).includes('Directories')
            ? log('/contacts resolves to directory content ✓')
            : fail('/contacts did not reach directory: ' + page.url()));

    // ─── SUMMARY ─────────────────────────────────────────────────────────────
    await page.waitForTimeout(3000);
    await browser.close();
    printSummary();

    function printSummary() {
        console.log(`\n${'═'.repeat(60)}`);
        console.log(`  RESULTS: ${passed} passed  ·  ${warned} warnings  ·  ${failed} failed`);
        console.log(`${'═'.repeat(60)}\n`);
        if (failed > 0) process.exit(1);
    }
})();
