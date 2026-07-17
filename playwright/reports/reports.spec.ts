import { test } from '../fixtures/app.fixture';
import { assertPageLoads, assertExportEndpoint } from '../helpers/module';

test.describe('Reports Module', () => {
    test.use({ userKey: 'staff' });

    test('reports library loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/reports', { bodyPattern: /report/i });
    });

    test('report detail page loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/reports/revenue-collections', { bodyPattern: /revenue|report|collection/i });
    });

    test('report schedule page loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/reports/schedule', { bodyPattern: /schedule|report/i });
    });

    test('report export endpoint responds', async ({ page, authenticatedPage }) => {
        await assertExportEndpoint(page, '/reports/revenue-collections/export');
    });
});
