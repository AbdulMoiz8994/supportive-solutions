import { test } from '../fixtures/app.fixture';
import { assertPageLoads } from '../helpers/module';

test.describe('Compliance Module', () => {
    test.use({ userKey: 'staff' });

    test('compliance page loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/compliance', { bodyPattern: /compliance|document/i });
    });

    test('audit view page loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/audit-view', { bodyPattern: /audit/i });
    });
});
