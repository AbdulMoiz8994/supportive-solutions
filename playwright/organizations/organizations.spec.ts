import { test, expect } from '../fixtures/app.fixture';
import { assertPageLoads, assertForbidden } from '../helpers/module';
import { login } from '../helpers/auth';

test.describe('Organizations Module', () => {
    test.use({ userKey: 'superAdmin' });

    test('organizations placeholder loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/organizations');
        await expect(page).toHaveURL(/organizations/);
    });
});

test.describe('Organizations — Authorization', () => {
    test('admin cannot access organizations', async ({ page }) => {
        await login(page, 'admin');
        await assertForbidden(page, '/organizations');
    });
});
