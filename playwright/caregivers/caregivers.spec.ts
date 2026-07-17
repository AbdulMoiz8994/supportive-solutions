import { test } from '../fixtures/app.fixture';
import { assertPageLoads, assertExportEndpoint } from '../helpers/module';

test.describe('Caregivers Module', () => {
    test.use({ userKey: 'staff' });

    test('caregivers listing page loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/caregivers', { bodyPattern: /caregiver|employee/i });
    });

    test('caregivers create wizard loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/caregivers/create');
    });

    test('caregivers export endpoint responds', async ({ page, authenticatedPage }) => {
        await assertExportEndpoint(page, '/caregivers/export');
    });

    test('caregiver detail from listing', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/caregivers');
        const link = page.locator('table tbody tr a, a[href*="/caregivers/"]').first();
        if (await link.count() > 0) {
            const href = await link.getAttribute('href');
            if (href && !href.includes('create') && !href.includes('export')) {
                await link.click();
                await assertPageLoads(page, page.url());
            }
        }
    });
});
