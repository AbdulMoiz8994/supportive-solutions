import { test, expect } from '../fixtures/app.fixture';
import { assertPageLoads, assertForbidden } from '../helpers/module';
import { login } from '../helpers/auth';

test.describe('Dashboard Module', () => {
    test.use({ userKey: 'admin' });

    test('dashboard page loads with stat cards', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/dashboard', { bodyPattern: /dashboard|client|intake|billing|employee/i });
    });

    test('sidebar badges endpoint does not fail', async ({ page, authenticatedPage }) => {
        const response = await page.request.get('/sidebar/badges');
        expect(response.status()).toBeLessThan(500);
    });

    test('sidebar is visible', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/dashboard');
        await expect(page.locator('nav, aside').first()).toBeVisible();
    });
});

test.describe('Dashboard — Employee Authorization', () => {
    test('employee cannot access office team dashboard route', async ({ page }) => {
        await login(page, 'employee');
        await assertForbidden(page, '/dashboard');
    });
});
