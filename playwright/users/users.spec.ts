import { test } from '../fixtures/app.fixture';
import { assertPageLoads, assertForbidden } from '../helpers/module';
import { login } from '../helpers/auth';

test.describe('Platform Users Module', () => {
    test.use({ userKey: 'superAdmin' });

    test('users listing loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/users', { bodyPattern: /user/i });
    });
});

test.describe('Platform Users — Authorization', () => {
    test('admin cannot access platform users', async ({ page }) => {
        await login(page, 'admin');
        await assertForbidden(page, '/users');
    });
});
