import { test, expect } from '../fixtures/app.fixture';
import { assertPageLoads, assertExportEndpoint, assertForbidden } from '../helpers/module';
import { login } from '../helpers/auth';

test.describe('Billing Claims Audit Module', () => {
    test.use({ userKey: 'staff' });

    test('billing claims audit index loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/billing-claims-audit', { bodyPattern: /billing|claim|audit/i });
    });

    test('aging report loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/billing-claims-audit/aging');
    });

    test('export endpoint responds', async ({ page, authenticatedPage }) => {
        await assertExportEndpoint(page, '/billing-claims-audit/export');
    });

    test('aging export endpoint responds', async ({ page, authenticatedPage }) => {
        await assertExportEndpoint(page, '/billing-claims-audit/aging/export');
    });

    test('claim detail from listing', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/billing-claims-audit');
        const link = page.locator('table tbody tr a, a[href*="/billing-claims-audit/"]').first();
        if (await link.count() > 0) {
            const href = await link.getAttribute('href');
            if (href && !href.includes('aging') && !href.includes('export')) {
                await link.click();
                await assertPageLoads(page, page.url());
            }
        }
    });
});

test.describe('Billing Claims Audit — Authorization', () => {
    test('employee cannot access billing claims audit', async ({ page }) => {
        await login(page, 'employee');
        await assertForbidden(page, '/billing-claims-audit');
    });
});

test.describe('Legacy Billing Module', () => {
    test.use({ userKey: 'admin' });

    test('legacy billing route returns 404', async ({ page, authenticatedPage }) => {
        const response = await page.request.get('/billing');
        expect(response.status()).toBe(404);
    });
});
