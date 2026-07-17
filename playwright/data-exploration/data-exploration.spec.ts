import { test } from '../fixtures/app.fixture';
import { assertPageLoads, assertExportEndpoint } from '../helpers/module';

test.describe('Data Exploration Module', () => {
    test.use({ userKey: 'staff' });

    test('data exploration page loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/data-exploration', { bodyPattern: /exploration|data|query/i });
    });

    test('exploration alias redirects', async ({ page, authenticatedPage }) => {
        await page.goto('/exploration');
        await page.waitForURL(/data-exploration|exploration/);
    });

    test('export endpoint responds', async ({ page, authenticatedPage }) => {
        await assertExportEndpoint(page, '/data-exploration/export');
    });
});
