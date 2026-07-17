import { test } from '../fixtures/app.fixture';
import { assertPageLoads } from '../helpers/module';

test.describe('Messages Module', () => {
    test.use({ userKey: 'employee' });

    test('messages listing loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/messages', { bodyPattern: /message/i });
    });
});

test.describe('Messages — Staff Access', () => {
    test.use({ userKey: 'staff' });

    test('staff can access messages', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/messages');
    });
});
