import { test } from '../fixtures/app.fixture';
import { assertPageLoads } from '../helpers/module';

test.describe('Employees Module', () => {
    test.use({ userKey: 'staff' });

    test('employees listing loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/employees', { bodyPattern: /employee/i });
    });

    test('employee detail from listing', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/employees');
        const link = page.locator('table tbody tr a, a[href*="/employees/"]').first();
        if (await link.count() > 0) {
            await link.click();
            await assertPageLoads(page, page.url());
        }
    });
});
