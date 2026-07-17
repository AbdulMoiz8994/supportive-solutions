import { test } from '../fixtures/app.fixture';
import { assertPageLoads } from '../helpers/module';

test.describe('Forms Module', () => {
    test.use({ userKey: 'staff' });

    test('forms tracking listing loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/forms', { bodyPattern: /form/i });
    });

    test('dashboard forms redirects to forms', async ({ page, authenticatedPage }) => {
        await page.goto('/dashboard/forms');
        await page.waitForURL(/\/forms/);
    });
});
