import { test } from '../fixtures/app.fixture';
import { assertPageLoads } from '../helpers/module';

test.describe('Authorizations Module', () => {
    test.use({ userKey: 'staff' });

    test('authorizations page loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/authorizations', { bodyPattern: /authorization/i });
    });
});
