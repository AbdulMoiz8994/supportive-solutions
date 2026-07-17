import { test, expect } from '../fixtures/app.fixture';
import { assertPageLoads } from '../helpers/module';

test.describe('Clients — Extended', () => {
    test.use({ userKey: 'staff' });

    test('client detail from listing', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/clients');
        const link = page.locator('table tbody tr a, a[href*="/clients/"]').first();
        if (await link.count() > 0) {
            const href = await link.getAttribute('href');
            if (href && !href.includes('create') && !href.includes('export')) {
                await link.click();
                await assertPageLoads(page, page.url());
            }
        }
    });

    test('clients export endpoint', async ({ page, authenticatedPage }) => {
        const response = await page.request.get('/clients/export');
        expect(response.status()).toBeLessThan(500);
    });
});
