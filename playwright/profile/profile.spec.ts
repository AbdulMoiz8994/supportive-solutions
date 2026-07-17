import { test } from '../fixtures/app.fixture';
import { assertPageLoads } from '../helpers/module';

test.describe('Profile Module', () => {
    test.use({ userKey: 'staff' });

    test('profile page loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/profile', { bodyPattern: /profile|account/i });
    });
});

test.describe('Profile — All Roles', () => {
    test.use({ userKey: 'employee' });

    test('employee can access profile', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/profile');
    });
});
