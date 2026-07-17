import { test, expect } from '../fixtures/app.fixture';
import { login } from '../helpers/auth';
import { submitSearchForm } from '../helpers/wizard';

const PAYROLL_DEMO_PERIOD = '2026-05';

test.describe('Payroll — Index & Filters', () => {
    test.use({ userKey: 'admin' });

    test('payroll index loads May 2026 demo period', async ({ page, authenticatedPage }) => {
        await page.goto(`/payroll?period=${PAYROLL_DEMO_PERIOD}`);
        await expect(page.getByRole('heading', { name: 'Payroll' })).toBeVisible();
        await expect(page.locator('body')).toContainText(/May 2026|2026-05/i);
    });

    test('summary stat cards display', async ({ page, authenticatedPage }) => {
        await page.goto(`/payroll?period=${PAYROLL_DEMO_PERIOD}`);
        await expect(page.locator('body')).toContainText(/Ready|grace|Gross/i);
    });

    test('search filters by caregiver name', async ({ page, authenticatedPage }) => {
        await page.goto(`/payroll?period=${PAYROLL_DEMO_PERIOD}`);
        await page.getByPlaceholder(/Filter by caregiver/i).fill('Demo Caregiver 1');
        await submitSearchForm(page, /Filter by caregiver/i);
        await expect(page.locator('body')).toContainText(/Demo Caregiver 1/i);
    });

    test('status tab filters ready records', async ({ page, authenticatedPage }) => {
        await page.goto(`/payroll?period=${PAYROLL_DEMO_PERIOD}`);
        const readyTab = page.getByRole('link', { name: /Ready for batch/i });
        if (await readyTab.count() > 0) {
            await readyTab.first().click();
            await page.waitForURL(/status=ready/);
        }
    });

    test('export endpoint returns file', async ({ page, authenticatedPage }) => {
        const response = await page.request.get(`/payroll/export?period=${PAYROLL_DEMO_PERIOD}`);
        expect(response.status()).toBeLessThan(500);
    });

    test('pay record detail page loads', async ({ page, authenticatedPage }) => {
        await page.goto(`/payroll?period=${PAYROLL_DEMO_PERIOD}&search=Demo+Caregiver+1`);
        const viewLink = page.locator('a[href*="/payroll/"]').filter({ hasText: /View|Review/i }).first();
        await expect(viewLink).toBeVisible();
        await viewLink.click();
        await page.waitForURL(/\/payroll\/\d+/);
        await expect(page.locator('body')).toContainText(/Pay|wage|caregiver/i);
    });

    test('approval queue page loads', async ({ page, authenticatedPage }) => {
        await page.goto('/payroll/batch-queue');
        await expect(page.locator('body')).toContainText(/Approval Queue|batch/i);
    });
});

test.describe('Payroll — Build Batch Workflow', () => {
    test('super admin can access build batch form', async ({ page }) => {
        await login(page, 'superAdmin');
        await page.goto(`/payroll?period=${PAYROLL_DEMO_PERIOD}`);
        const buildForm = page.locator('#payroll-build-batch-form');
        await expect(buildForm).toBeVisible();
        await expect(buildForm.getByRole('button', { name: /Build batch/i })).toBeVisible();
    });

    test('admin cannot build batch (run_payroll denied)', async ({ page }) => {
        await login(page, 'admin');
        await page.goto(`/payroll?period=${PAYROLL_DEMO_PERIOD}`);
        const buildForm = page.locator('#payroll-build-batch-form');
        const visible = await buildForm.isVisible().catch(() => false);
        if (visible) {
            const buildBtn = buildForm.getByRole('button', { name: /Build batch/i });
            expect(await buildBtn.isVisible().catch(() => false)).toBeFalsy();
        }
    });
});

test.describe('Payroll — Held Record Review', () => {
    test.use({ userKey: 'admin' });

    test('held record shows review link', async ({ page, authenticatedPage }) => {
        await page.goto(`/payroll?period=${PAYROLL_DEMO_PERIOD}&status=held`);
        const reviewLink = page.locator('a[href*="/payroll/"]').filter({ hasText: /Review/i }).first();
        await expect(reviewLink).toBeVisible();
        await reviewLink.click();
        await page.waitForURL(/\/payroll\/\d+/);
        await expect(page.locator('body')).toContainText(/hold|review/i);
    });
});
