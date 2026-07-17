import { test } from '../fixtures/app.fixture';
import { assertPageLoads } from '../helpers/module';

test.describe('Leads Module', () => {
    test.use({ userKey: 'staff' });

    test('leads listing loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/leads', { bodyPattern: /intake|lead|name/i });
    });

    test('lead detail from listing', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/leads');
        const link = page.locator('table tbody tr a, a[href*="/leads/"]').first();
        if (await link.count() > 0) {
            await link.click();
            await assertPageLoads(page, page.url());
        }
    });
});
