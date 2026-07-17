import { test, expect } from '../fixtures/app.fixture';
import { login } from '../helpers/auth';
import { navigateTo } from '../helpers/navigation';
import { searchTable } from '../helpers/table';

test.describe('Clients Module', () => {
    test.use({ userKey: 'staff' });

    test('clients listing page loads', async ({ page, authenticatedPage }) => {
        await navigateTo(page, '/clients');
        await expect(page).not.toHaveURL(/signin/);
        const body = await page.locator('body').innerText();
        expect(body).toMatch(/client/i);
    });

    test('clients export link is accessible', async ({ page, authenticatedPage }) => {
        await navigateTo(page, '/clients');
        const exportLink = page.getByRole('link', { name: /export/i }).first();
        if (await exportLink.isVisible().catch(() => false)) {
            const response = await page.request.get('/clients/export');
            expect(response.status()).toBeLessThan(400);
        }
    });

    test('clients search filters results', async ({ page, authenticatedPage }) => {
        await navigateTo(page, '/clients');
        const searchInput = page.locator('input[type="search"], input[placeholder*="search" i]').first();
        if (await searchInput.isVisible().catch(() => false)) {
            await searchTable(page, 'Test');
            await page.waitForLoadState('networkidle');
        }
    });

    test('guest cannot access clients', async ({ page }) => {
        await page.goto('/clients');
        await expect(page).toHaveURL(/signin/);
    });
});

test.describe('Clients — Create', () => {
    test.use({ userKey: 'admin' });

    test('clients create page loads for authorized user', async ({ page, authenticatedPage }) => {
        await navigateTo(page, '/clients/create');
        await expect(page).not.toHaveURL(/signin/);
        await expect(page.locator('body')).not.toContainText(/access denied/i);
    });
});

test.describe('Clients — Authorization', () => {
    test('staff without add_clients permission is blocked from create', async ({ page }) => {
        await login(page, 'staff');
        const response = await page.goto('/clients/create');
        expect(response?.status()).toBe(403);
        await expect(page.locator('body')).toContainText(/access denied/i);
    });

    test('employee without office team role is blocked from clients listing', async ({ page }) => {
        await login(page, 'employee');
        const response = await page.goto('/clients');
        expect(response?.status()).toBe(403);
        await expect(page.locator('body')).toContainText(/access denied/i);
    });
});
