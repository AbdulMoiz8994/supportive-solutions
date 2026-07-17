import { test } from '../fixtures/app.fixture';
import { assertPageLoads } from '../helpers/module';

test.describe('Directory Module', () => {
    test.use({ userKey: 'staff' });

    test('directory listing loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/directory', { bodyPattern: /directory|contact/i });
    });

    test('directory create page loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/directory/create');
    });

    test('contacts redirects to directory', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/contacts');
        await page.waitForURL(/directory/);
    });

    test('directory detail from listing', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/directory');
        const link = page.locator('table tbody tr a, a[href*="/directory/"]').first();
        if (await link.count() > 0) {
            await link.click();
            await assertPageLoads(page, page.url());
        }
    });
});
