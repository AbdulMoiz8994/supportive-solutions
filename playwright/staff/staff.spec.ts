import { test } from '../fixtures/app.fixture';
import { assertPageLoads, assertExportEndpoint, assertForbidden } from '../helpers/module';
import { login } from '../helpers/auth';

test.describe('Staff Module', () => {
    test.use({ userKey: 'admin' });

    test('staff listing loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/staff', { bodyPattern: /staff/i });
    });

    test('staff create page loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/staff/create');
    });

    test('staff detail from listing', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/staff');
        const link = page.locator('table tbody tr a, a[href*="/staff/"]').filter({ hasNot: page.locator('text=/agent/i') }).first();
        if (await link.count() > 0) {
            const href = await link.getAttribute('href');
            if (href && !href.includes('/agents')) {
                await link.click();
                await assertPageLoads(page, page.url());
            }
        }
    });
});

test.describe('Staff — Authorization', () => {
    test('staff can view staff listing but not create', async ({ page }) => {
        await login(page, 'staff');
        await assertPageLoads(page, '/staff');
        await assertForbidden(page, '/staff/create');
    });
});
