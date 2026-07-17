import { test } from '../fixtures/app.fixture';
import { assertPageLoads, assertExportEndpoint } from '../helpers/module';

test.describe('Schedule Module', () => {
    test.use({ userKey: 'staff' });

    test('schedule list loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/schedule', { bodyPattern: /schedule|calendar/i });
    });

    test('schedule board loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/schedule/board');
    });

    test('calendar page loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/calendar');
    });

    test('schedule create page loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/schedule/create');
    });

    test('schedule export endpoint', async ({ page, authenticatedPage }) => {
        await assertExportEndpoint(page, '/schedule/export');
    });

    test('work-shifts redirects to schedule board', async ({ page, authenticatedPage }) => {
        await page.goto('/work-shifts');
        await page.waitForURL(/schedule\/board/);
    });
});

test.describe('Schedule — Employee Access', () => {
    test.use({ userKey: 'employee' });

    test('employee can access schedule board', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/schedule/board');
    });
});
