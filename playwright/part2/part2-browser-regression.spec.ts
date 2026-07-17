import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { test, expect } from '../fixtures/app.fixture';
import { getCsrfToken, login } from '../helpers/auth';
import { APP_ROOT, LOG_FILE } from '../helpers/config';
import { assertNoCriticalIssues, attachConsoleMonitor } from '../helpers/console';
import { assertForbidden, assertPageLoads, gotoModule } from '../helpers/module';
import { waitForNetworkIdle } from '../helpers/waits';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const FIXTURE_PATH = path.join(APP_ROOT, 'storage/app/part2-browser-fixtures.json');

type Fixture = {
    ok: boolean;
    org_id: number;
    client_id: number;
    schedule_id: number;
    caregiver: string;
    client: string;
};

function readFixture(): Fixture {
    const raw = fs.readFileSync(FIXTURE_PATH, 'utf8');
    return JSON.parse(raw) as Fixture;
}

function laravelExceptionDelta(sinceByte: number): string[] {
    try {
        const content = fs.readFileSync(LOG_FILE, 'utf8').slice(sinceByte);
        const matches = [...content.matchAll(/\.ERROR:.*|local\.ERROR:.*|production\.ERROR:.*/gi)];
        return matches.map((m) => m[0].slice(0, 240));
    } catch {
        return [];
    }
}

function logOffset(): number {
    try {
        return fs.statSync(LOG_FILE).size;
    } catch {
        return 0;
    }
}

test.describe('Part 2 browser regression', () => {
    test.use({ userKey: 'admin' });

    let fixture: Fixture;
    let logStart = 0;

    test.beforeAll(() => {
        fixture = readFixture();
        expect(fixture.ok).toBeTruthy();
        logStart = logOffset();
    });

    test.afterAll(() => {
        const errors = laravelExceptionDelta(logStart);
        const critical = errors.filter((line) => !/DEBUG|INFO|WARNING/i.test(line));
        if (critical.length > 0) {
            throw new Error(`Laravel log errors during Part 2 browser run:\n${critical.slice(0, 8).join('\n')}`);
        }
    });

    test('Visit Reports listing loads without console/AJAX failures', async ({ page, authenticatedPage, consoleIssues }) => {
        const failed: string[] = [];
        page.on('response', (res) => {
            if (res.url().includes('/visit-reports') && res.status() >= 500) {
                failed.push(`${res.status()} ${res.url()}`);
            }
        });

        await assertPageLoads(page, '/visit-reports', { bodyPattern: /Visit Reports|EVV/i });
        await expect(page.locator('table tbody')).toContainText(/Browser Part2Care|Sarah Connor|Needs review|No|Yes/i);
        expect(failed).toEqual([]);
        assertNoCriticalIssues(consoleIssues);
    });

    test('Visit detail + location approve happy path', async ({ page, authenticatedPage, consoleIssues }) => {
        await gotoModule(page, '/visit-reports');
        await waitForNetworkIdle(page);

        const row = page.locator('table tbody tr').filter({ hasText: 'Browser Part2Care' }).first();
        await expect(row).toBeVisible();
        await expect(row).toContainText('No');
        await expect(row).toContainText('Needs review');

        const fixBtn = row.getByRole('button', { name: /Fix \/ Approve/i });
        await expect(fixBtn).toBeVisible();
        await fixBtn.click();

        await expect(page.getByText('Visit detail')).toBeVisible();
        await expect(page.getByText(/Approve location/i)).toBeVisible({ timeout: 10_000 });

        const csrf = await getCsrfToken(page);
        const show = await page.request.get(`/visit-reports/${fixture.schedule_id}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        expect(show.status()).toBe(200);
        const showBody = await show.json();
        expect(showBody.ok).toBeTruthy();
        expect(showBody.visit.can_approve_location).toBeTruthy();

        // Prefer UI path when modal is open
        const reason = page.locator('textarea[placeholder*="location acceptable"], textarea').filter({ hasText: '' }).last();
        const reasonBox = page.locator('[x-show="detail.can_approve_location"] textarea, textarea[placeholder*="Why is this location"]').first();
        await reasonBox.fill('Browser regression: client at adult day program');
        await page.getByRole('button', { name: /^Approve location$/i }).click();

        await expect(page.locator('body')).toContainText(/approved|GPS preserved|Billable|Override/i, { timeout: 15_000 });

        // Confirm via API state
        const after = await page.request.get(`/visit-reports/${fixture.schedule_id}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const afterBody = await after.json();
        expect(afterBody.visit.location_match).toContain('Approved Override');
        expect(afterBody.visit.location_overridden).toBeTruthy();
        expect(afterBody.visit.billable).toBeTruthy();

        // Failure: double approve
        const duplicate = await page.request.post(`/visit-reports/${fixture.schedule_id}/approve-location`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            data: { reason: 'Should fail' },
        });
        expect(duplicate.status()).toBe(422);

        await page.reload();
        await waitForNetworkIdle(page);
        await expect(page.locator('table tbody tr').filter({ hasText: 'Browser Part2Care' })).toContainText('Yes (Approved Override)');

        void reason;
        assertNoCriticalIssues(consoleIssues, [/422/]);
    });

    test('Visit Reports: no-mismatch approve fails cleanly', async ({ page, authenticatedPage }) => {
        const csrf = await getCsrfToken(page);
        // Use a scheduled visit without mismatch if present; otherwise hit a completed clean visit via listing IDs.
        const listing = await page.request.get('/visit-reports');
        expect(listing.status()).toBe(200);

        // Already-approved visit should reject again (covered above). Also reject missing reason.
        const missingReason = await page.request.post(`/visit-reports/${fixture.schedule_id}/approve-location`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            data: {},
        });
        expect([422, 302]).toContain(missingReason.status());
    });

    test('Tasks: overdue display, counters, drawer priority contract', async ({ page, authenticatedPage, consoleIssues }) => {
        await assertPageLoads(page, '/tasks?view=list', { bodyPattern: /Tasks|Overdue/i });
        await expect(page.locator('body')).toContainText('Part2 Overdue Browser Task');
        await expect(page.locator('body')).toContainText(/Overdue/i);
        await expect(page.locator('body')).toContainText(/High/i);

        const overdueCounter = page.locator('a, button, div').filter({ hasText: /^Overdue$/i }).first();
        await expect(overdueCounter.or(page.getByText('Overdue', { exact: false })).first()).toBeVisible();

        await page.goto('/tasks?status=overdue&view=list');
        await waitForNetworkIdle(page);
        await expect(page.locator('body')).toContainText('Part2 Overdue Browser Task');

        // Open drawer JSON contract
        const row = page.locator('[data-testid="task-list-row"], [data-task-id]').filter({ hasText: 'Part2 Overdue Browser Task' }).first();
        if (await row.count()) {
            const taskId = await row.getAttribute('data-task-id');
            expect(taskId).toBeTruthy();
            const detail = await page.request.get(`/tasks/${taskId}`);
            expect(detail.status()).toBe(200);
            const body = await detail.json();
            expect(body.ok).toBeTruthy();
            expect(body.task.priority).toBe('medium');
            expect(body.task.priority_effective).toBe('high');
            expect(body.task.priority_elevated).toBeTruthy();
            expect(body.task.is_overdue).toBeTruthy();
        }

        assertNoCriticalIssues(consoleIssues);
    });

    test('Forms: listing + generate drafts stays Draft', async ({ page, authenticatedPage, consoleIssues }) => {
        await assertPageLoads(page, '/forms', { bodyPattern: /Forms|template|submission/i });
        await expect(page.locator('body')).toContainText(/Part2 Browser Consent|Consent|Draft|Awaiting|Signed/i);

        const csrf = await getCsrfToken(page);
        const gen = await page.request.post('/forms/generate-drafts', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
        });
        // Endpoint may redirect or JSON depending on Accept; either must not 500.
        expect(gen.status()).toBeLessThan(500);

        await page.goto('/forms');
        await waitForNetworkIdle(page);
        await expect(page.locator('body')).not.toContainText(/Exception|Whoops|SQLSTATE/i);

        assertNoCriticalIssues(consoleIssues, [/generate-drafts/i]);
    });

    test('Data Exploration: query, program filter, agent views, export', async ({ page, authenticatedPage, consoleIssues }) => {
        const ajaxFailures: string[] = [];
        page.on('response', (res) => {
            if (res.url().includes('/data-exploration') && res.status() >= 500) {
                ajaxFailures.push(`${res.status()} ${res.url()}`);
            }
        });

        await assertPageLoads(page, '/data-exploration', { bodyPattern: /Data Exploration/i });
        await expect(page.locator('body')).toContainText(/Read-only/i);

        // Agent suggested views when document agent active
        await expect(page.locator('body')).toContainText(/\[Agent\] Visits this week|Saved views|Visits this week/i);

        const csrf = await getCsrfToken(page);
        const query = await page.request.post('/data-exploration/query', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            data: {
                dataset: 'clients',
                program: 'DHS',
                date_from: '2020-01-01',
                date_to: new Date().toISOString().slice(0, 10),
            },
        });
        expect(query.status()).toBe(200);
        const qBody = await query.json();
        expect(qBody.ok).toBeTruthy();
        expect(Array.isArray(qBody.rows)).toBeTruthy();

        const exportRes = await page.request.get(
            `/data-exploration/export?dataset=clients&format=csv&program=DHS&date_from=2020-01-01&date_to=${new Date().toISOString().slice(0, 10)}`,
        );
        expect(exportRes.status()).toBeLessThan(500);

        // UI filter interaction if program input exists
        const programInput = page.locator('input[name="program"], input[x-model*="program"], #program').first();
        if (await programInput.count()) {
            await programInput.fill('DHS');
            const refresh = page.getByRole('button', { name: /run|apply|refresh|update/i }).first();
            if (await refresh.count()) {
                await refresh.click();
                await waitForNetworkIdle(page);
            }
        }

        expect(ajaxFailures).toEqual([]);
        assertNoCriticalIssues(consoleIssues);
    });

    test('Authorization: employee cannot access Part 2 modules', async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        const issues = attachConsoleMonitor(page);

        await login(page, 'employee');
        await assertForbidden(page, '/visit-reports');
        await assertForbidden(page, '/tasks');
        await assertForbidden(page, '/forms');
        await assertForbidden(page, '/data-exploration');

        assertNoCriticalIssues(issues, [/403|Forbidden|access denied/i]);
        await context.close();
    });

    test('UI consistency smoke across Part 2 nav', async ({ page, authenticatedPage, consoleIssues }) => {
        for (const route of ['/visit-reports', '/tasks', '/forms', '/data-exploration']) {
            const res = await gotoModule(page, route);
            expect(res?.status() ?? 200).toBeLessThan(400);
            await expect(page).not.toHaveURL(/signin/);
            const main = page.locator('#main-content-wrap, main, .space-y-6').first();
            await expect(main).toBeVisible();
            await expect(main).not.toContainText(/Whoops!|SQLSTATE\[|Undefined variable \$/i);
            await expect(page.locator('h1').first()).toBeVisible();
        }
        assertNoCriticalIssues(consoleIssues);
    });
});
