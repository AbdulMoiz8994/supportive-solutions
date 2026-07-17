import { test } from '../fixtures/app.fixture';
import { assertPageLoads } from '../helpers/module';

test.describe('Visit Reports Module', () => {
    test.use({ userKey: 'staff' });

    test('visit reports listing loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/visit-reports', { bodyPattern: /visit|report/i });
    });

    test('reports visit redirects to visit-reports', async ({ page, authenticatedPage }) => {
        await page.goto('/reports/visit');
        await page.waitForURL(/visit-reports/);
    });

    test('visit report detail from listing', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/visit-reports');
        const link = page.locator('table tbody tr a, a[href*="/visit-reports/"]').first();
        if (await link.count() > 0) {
            await link.click();
            await assertPageLoads(page, page.url());
        }
    });
});
