import { test } from '../fixtures/app.fixture';
import { assertPageLoads } from '../helpers/module';

test.describe('Placeholder Pages', () => {
    test.use({ userKey: 'staff' });

    test('marketing coming soon loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/marketing', { bodyPattern: /marketing|coming soon/i });
    });

    test('events coming soon loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/events', { bodyPattern: /event|coming soon/i });
    });

    test('client status placeholder loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/clients/status');
    });

    test('client documents placeholder loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/clients/documents');
    });

    test('client appointments redirects to schedule board', async ({ page, authenticatedPage }) => {
        await page.goto('/clients/appointments');
        await page.waitForURL(/schedule\/board/);
    });
});
