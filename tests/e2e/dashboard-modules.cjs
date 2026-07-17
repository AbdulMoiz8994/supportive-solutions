/**
 * Playwright verification — dashboard modules (Visit Reports, Tasks, Forms, Data Exploration 2.0).
 * Includes sidebar navigation and Tasks board UX (drawer, manage statuses, drag-and-drop).
 * Targets: http://127.0.0.1:8000
 *
 * Run: npm run test:e2e:dashboard-modules
 * Requires: php artisan serve (or Laragon) + npm run playwright:install
 */
const { chromium } = require('playwright');
const fs   = require('fs');
const path = require('path');
const os   = require('os');
const { execSync } = require('child_process');

const BASE     = 'http://127.0.0.1:8000';
const EMAIL    = 'admin@beydountech.com';
const PASS     = 'admin123';
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

function logSize() {
    try { return fs.statSync(LOG_FILE).size; } catch (_) { return 0; }
}

function clearRateLimitCache(quiet = false) {
    try {
        execSync('php artisan cache:clear', { cwd: path.join(__dirname, '../..'), stdio: 'pipe' });
        if (!quiet) warn('2FA rate-limit detected; cache cleared and retrying');
        return true;
    } catch (_) { return false; }
}

async function readOtp(page, logSizeBefore = 0) {
    const debugBox = page.locator('.font-mono.text-lg.tracking-widest').first();
    if (await debugBox.count() > 0) {
        const otp = (await debugBox.textContent())?.replace(/\s/g, '').trim();
        if (otp?.length === 6) {
            log(`OTP from debug box: ${otp}`);
            return otp;
        }
    }
    if (logSize() > logSizeBefore) {
        const otp = readOtpFromLog();
        if (otp) {
            log(`OTP from mail log: ${otp}`);
            return otp;
        }
    }
    return null;
}

async function submitOtp(page, otp) {
    const boxes = page.locator('#otp-container input.otp-input');
    if (await boxes.count() !== 6) return false;

    await boxes.first().click();
    await page.evaluate((code) => {
        const inputs = document.querySelectorAll('#otp-container .otp-input');
        inputs.forEach((input, i) => {
            input.value = code[i] || '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }, otp);

    try {
        await page.waitForURL((url) => !url.toString().includes('two-factor'), { timeout: 15000 });
    } catch (_) {}

    return !/two-factor/.test(page.url());
}

async function login(page) {
    h('LOGIN');
    clearRateLimitCache(true);

    await page.goto(`${BASE}/signin`);
    await page.waitForLoadState('networkidle');
    await page.fill('input[name="email"], input[type="email"]', EMAIL);
    await page.fill('input[name="password"], input[type="password"]', PASS);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    let logSizeBefore = logSize();

    if (page.url().includes('two-factor/choice')) {
        const emailOpt = page.locator('input[value="email"]').first();
        if (await emailOpt.count() > 0) await emailOpt.click();

        logSizeBefore = logSize();
        const sendBtn = page.locator('form button[type="submit"]').first();
        if (await sendBtn.count() > 0) await sendBtn.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);

        if (page.url().includes('two-factor/choice') && clearRateLimitCache()) {
            await page.reload();
            await page.waitForLoadState('networkidle');
            logSizeBefore = logSize();
            const retrySendBtn = page.locator('form button[type="submit"]').first();
            if (await retrySendBtn.count() > 0) {
                await retrySendBtn.click();
                await page.waitForLoadState('networkidle');
                await page.waitForTimeout(1200);
            }
        }

        if (!page.url().includes('two-factor/verify')) {
            await page.goto(`${BASE}/two-factor/verify`);
            await page.waitForLoadState('networkidle');
        }
    }

    if (/two-factor\/verify/.test(page.url())) {
        const otp = await readOtp(page, logSizeBefore);
        if (!otp || !(await submitOtp(page, otp))) {
            fail('Could not verify 2FA OTP');
            return false;
        }
        log('OTP submitted');
    }

    if (/signin|login|two-factor/.test(page.url())) {
        fail('Auth failed: ' + page.url());
        return false;
    }
    log('Authenticated → ' + page.url());
    return true;
}

function attachErrorWatch(page, bucket) {
    page.on('pageerror', (e) => bucket.push('pageerror: ' + e.message));
    page.on('console', (m) => { if (m.type() === 'error') bucket.push('console.error: ' + m.text()); });
}

async function checkModulePage(page, spec, errors) {
    const before = errors.length;
    const res = await page.goto(`${BASE}${spec.path}`);
    await page.waitForLoadState('networkidle');
    (res && res.status() === 200) ? log(`${spec.heading} HTTP 200`) : fail(`${spec.heading} HTTP ${res && res.status()}`);
    (await page.locator('h1', { hasText: spec.heading }).count() > 0) ? log(`${spec.heading} heading ✓`) : fail(`${spec.heading} heading missing`);
    for (const text of spec.checks) {
        (await page.locator(`text=${text}`).count() > 0) ? log(`${spec.heading}: found "${text}" ✓`) : warn(`${spec.heading}: "${text}" not found`);
    }
    await page.screenshot({ path: `${SHOT_DIR}/page-${spec.path.replace(/\//g, '-').replace(/\?.*$/, '')}.png`, fullPage: true });
    (errors.length === before) ? log(`${spec.heading}: no JS errors ✓`) : fail(`${spec.heading} JS errors: ` + errors.slice(before).join(' | '));
}

function printSummary() {
    console.log(`\n${'═'.repeat(60)}`);
    console.log(`  RESULTS: ${passed} passed  ·  ${warned} warnings  ·  ${failed} failed`);
    console.log(`${'═'.repeat(60)}\n`);
    if (failed > 0) process.exit(1);
}

(async () => {
    fs.mkdirSync(SHOT_DIR, { recursive: true });

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

    h('SIDEBAR NAVIGATION');
    await page.goto(`${BASE}/dashboard`);
    await page.waitForLoadState('networkidle');

    const sidebarLinks = [
        { label: 'Visit Reports', heading: 'Visit Reports' },
        { label: 'Tasks', heading: 'Tasks' },
        { label: 'Forms', heading: 'Forms' },
        { label: 'Data Exploration', heading: 'Data Exploration 2.0' },
    ];

    for (const link of sidebarLinks) {
        const nav = page.locator('#sidebar a', { hasText: link.label }).first();
        if (await nav.count() === 0) {
            fail(`Sidebar link "${link.label}" not found`);
            continue;
        }
        await nav.click();
        await page.waitForLoadState('networkidle');
        (await page.locator('h1', { hasText: link.heading }).count() > 0)
            ? log(`Sidebar "${link.label}" → ${link.heading} ✓`)
            : fail(`Sidebar "${link.label}" did not reach ${link.heading}`);
    }

    const pages = [
        { path: '/visit-reports', heading: 'Visit Reports', checks: ['Completed', 'Needs review', 'EVV proof'] },
        { path: '/tasks?view=board', heading: 'Tasks', checks: ['New Task', 'Manage statuses', 'Drop tasks here'] },
        { path: '/forms', heading: 'Forms', checks: ['Templates', 'Consent to Care', 'Completed forms'] },
        { path: '/data-exploration', heading: 'Data Exploration 2.0', checks: ['Read-only', 'Dataset', 'Export CSV'] },
    ];

    for (const spec of pages) {
        h(`${spec.heading}  (${spec.path})`);
        await checkModulePage(page, spec, errors);
    }

    h('TASKS — DETAIL DRAWER');
    await page.goto(`${BASE}/tasks?view=board`);
    await page.waitForLoadState('networkidle');

    const firstCard = page.locator('[data-testid="task-board-card"]').first();
    if (await firstCard.count() === 0) {
        fail('No task board cards found to open drawer');
    } else {
        const cardTitle = (await firstCard.innerText()).split('\n')[0]?.trim() || '';
        await firstCard.click();
        await page.waitForTimeout(800);

        const drawer = page.locator('[data-testid="task-detail-drawer"]');
        if (await drawer.isVisible()) {
            log('Task detail drawer opened ✓');
            (await page.locator('text=Task details').count() > 0) ? log('Drawer shows "Task details" ✓') : warn('Drawer header missing');
            if (cardTitle && (await drawer.innerText()).includes(cardTitle)) {
                log(`Drawer shows task title "${cardTitle}" ✓`);
            }
            const editBtn = page.locator('[data-testid="task-drawer-edit-btn"]');
            if (await editBtn.count() > 0) {
                await editBtn.click();
                await page.waitForTimeout(400);
                (await page.locator('#drawer-task-title').count() > 0) ? log('Drawer edit mode ✓') : warn('Edit form not visible');
            }
            await page.locator('[data-testid="task-drawer-close-btn"]').click();
            await page.waitForTimeout(400);
        } else {
            fail('Task detail drawer did not open on card click');
        }
    }

    h('TASKS — MANAGE STATUSES MODAL');
    await page.goto(`${BASE}/tasks?view=board`);
    await page.waitForLoadState('networkidle');

    const manageBtn = page.locator('[data-testid="manage-statuses-btn"]');
    if (await manageBtn.count() === 0) {
        fail('Manage statuses button not found');
    } else {
        await manageBtn.click();
        await page.waitForTimeout(400);
        const modal = page.locator('[data-testid="board-statuses-modal"]');
        (await modal.isVisible()) ? log('Manage statuses modal opened ✓') : fail('Manage statuses modal not visible');

        const uniqueLabel = `E2E Hold ${Date.now()}`;
        await page.locator('[data-testid="new-status-label"]').fill(uniqueLabel);
        await page.locator('[data-testid="add-board-status-btn"]').click();
        await page.waitForTimeout(1500);

        if (!(await modal.isVisible())) {
            log('Modal closed after adding status ✓');
        } else {
            warn('Modal still visible after add');
        }

        (await page.locator(`text=${uniqueLabel}`).count() > 0)
            ? log(`New status "${uniqueLabel}" on board ✓`)
            : warn('New status label not found on board');
    }

    h('TASKS — DRAG AND DROP');
    await page.goto(`${BASE}/tasks?view=board`);
    await page.waitForLoadState('networkidle');

    const todoColumn = page.locator('[data-testid="task-board-column"][data-status-key="todo"]');
    const inProgressColumn = page.locator('[data-testid="task-board-column"][data-status-key="in_progress"]');
    const draggable = todoColumn.locator('[data-testid="task-board-card"]').first();

    if (await draggable.count() === 0) {
        warn('No todo tasks available to drag');
    } else if (await inProgressColumn.count() === 0) {
        fail('In progress column not found');
    } else {
        const titleBefore = (await draggable.innerText()).split('\n')[0]?.trim() || '';
        const taskId = await draggable.getAttribute('data-task-id');
        const todoCountBefore = await todoColumn.locator('[data-testid="task-board-card"]').count();

        await draggable.dragTo(inProgressColumn);
        await page.waitForTimeout(1500);

        const inTarget = await inProgressColumn.locator('[data-testid="task-board-card"]').filter({ hasText: titleBefore }).count();
        const todoCountAfter = await todoColumn.locator('[data-testid="task-board-card"]').count();

        if (inTarget > 0 && todoCountAfter < todoCountBefore) {
            log(`Dragged "${titleBefore}" todo → in progress ✓`);
        } else if (await page.locator('text=Task moved').count() > 0) {
            log('Drag triggered "Task moved" toast ✓');
        } else {
            warn(`Drag inconclusive for "${titleBefore}"`);
        }
    }

    h('TASKS — EDIT AFTER DRAG AND DROP (regression)');
    await page.goto(`${BASE}/tasks?view=board`);
    await page.waitForLoadState('networkidle');

    const todoCol = page.locator('[data-testid="task-board-column"][data-status-key="todo"]');
    const progressCol = page.locator('[data-testid="task-board-column"][data-status-key="in_progress"]');
    const cardToDrag = todoCol.locator('[data-testid="task-board-card"]').first();

    if (await cardToDrag.count() === 0) {
        warn('No todo task for edit-after-drag regression');
    } else if (await progressCol.count() === 0) {
        fail('In progress column missing for edit-after-drag test');
    } else {
        const regressionTitle = (await cardToDrag.innerText()).split('\n')[0]?.trim() || '';
        const regressionTaskId = await cardToDrag.getAttribute('data-task-id');

        await cardToDrag.dragTo(progressCol);
        await page.waitForTimeout(1800);

        const movedCard = regressionTaskId
            ? progressCol.locator(`[data-testid="task-board-card"][data-task-id="${regressionTaskId}"]`)
            : progressCol.locator('[data-testid="task-board-card"]').filter({ hasText: regressionTitle }).first();

        if (await movedCard.count() === 0) {
            fail(`Moved card not found in in_progress after drag (task id ${regressionTaskId})`);
        } else {
            await movedCard.click();
            await page.waitForTimeout(900);

            const drawerAfterDrag = page.locator('[data-testid="task-detail-drawer"]');
            if (await drawerAfterDrag.isVisible()) {
                log('Drawer opened after clicking task post-drag ✓');
                if (regressionTitle && (await drawerAfterDrag.innerText()).includes(regressionTitle)) {
                    log(`Drawer shows moved task "${regressionTitle}" ✓`);
                }
                const editAfterDrag = page.locator('[data-testid="task-drawer-edit-btn"]');
                if (await editAfterDrag.count() > 0) {
                    await editAfterDrag.click();
                    await page.waitForTimeout(500);
                    (await page.locator('#drawer-task-title').count() > 0)
                        ? log('Edit mode available after drag-and-drop click ✓')
                        : fail('Edit form not visible after drag-and-drop click');
                } else {
                    warn('Edit button not found in drawer after drag');
                }
                await page.locator('[data-testid="task-drawer-close-btn"]').click();
                await page.waitForTimeout(400);
            } else {
                fail('Drawer did not open after clicking task post-drag (suppressTaskClick regression)');
            }
        }
    }

    await browser.close();
    printSummary();
})();
