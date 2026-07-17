import { test, expect } from '../fixtures/app.fixture';
import { assertPageLoads, assertForbidden } from '../helpers/module';
import { login } from '../helpers/auth';

test.describe('API Keys Module', () => {
    test.use({ userKey: 'superAdmin' });

    test('api keys page loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/api-keys', { bodyPattern: /api|key/i });
    });
});

test.describe('API Keys — Authorization', () => {
    test('admin cannot access super admin api keys page', async ({ page }) => {
        await login(page, 'admin');
        await assertForbidden(page, '/api-keys');
    });
});
