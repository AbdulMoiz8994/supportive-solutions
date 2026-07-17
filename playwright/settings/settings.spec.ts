import { test } from '../fixtures/app.fixture';
import { assertPageLoads, assertForbidden } from '../helpers/module';
import { login } from '../helpers/auth';

test.describe('Organization Settings Module', () => {
    test.use({ userKey: 'admin' });

    test('settings index loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/settings', { bodyPattern: /settings/i });
    });

    test('settings roles page loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/settings/roles', { bodyPattern: /role/i });
    });
});

test.describe('Settings — Authorization', () => {
    test('operations staff cannot access settings', async ({ page }) => {
        await login(page, 'staff');
        await assertForbidden(page, '/settings');
    });
});
