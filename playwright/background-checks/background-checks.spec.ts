import { test } from '../fixtures/app.fixture';
import { assertPageLoads } from '../helpers/module';

test.describe('Background Checks Module', () => {
    test.use({ userKey: 'staff' });

    test('background checks page loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/background-checks', { bodyPattern: /background|check/i });
    });
});
