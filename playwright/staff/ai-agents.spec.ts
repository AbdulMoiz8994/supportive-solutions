import { test } from '../fixtures/app.fixture';
import { assertPageLoads, assertExportEndpoint } from '../helpers/module';

test.describe('AI Agents Module', () => {
    test.use({ userKey: 'admin' });

    test('ai agents section loads from staff page', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/staff', { bodyPattern: /agent|staff/i });
    });

    test('ai agent create page loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/staff/agents/create');
    });

    test('ai agents export endpoint', async ({ page, authenticatedPage }) => {
        await assertExportEndpoint(page, '/staff/agents/export');
    });
});
