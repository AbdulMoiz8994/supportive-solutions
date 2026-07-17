import { test } from '../fixtures/app.fixture';
import { assertPageLoads, assertExportEndpoint, assertForbidden } from '../helpers/module';
import { login } from '../helpers/auth';

test.describe('Payroll Module', () => {
    test.use({ userKey: 'admin' });

    test('payroll index loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/payroll', { bodyPattern: /payroll|pay/i });
    });

    test('payroll batch queue loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/payroll/batch-queue');
    });

    test('payroll export endpoint', async ({ page, authenticatedPage }) => {
        await assertExportEndpoint(page, '/payroll/export');
    });

    test('payroll detail from listing', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/payroll');
        const link = page.locator('table tbody tr a, a[href*="/payroll/"]').first();
        if (await link.count() > 0) {
            const href = await link.getAttribute('href');
            if (href && !href.includes('batch') && !href.includes('export')) {
                await link.click();
                await assertPageLoads(page, page.url());
            }
        }
    });
});

test.describe('Payroll — Authorization', () => {
    test('operations staff cannot access payroll', async ({ page }) => {
        await login(page, 'staff');
        await assertForbidden(page, '/payroll');
    });
});
