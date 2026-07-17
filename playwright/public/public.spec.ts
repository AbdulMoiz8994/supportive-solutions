import { test, expect } from '@playwright/test';

test.describe('Public Routes', () => {
    test('maintenance page loads', async ({ page }) => {
        await page.goto('/maintenance');
        await expect(page.locator('body')).toBeVisible();
    });

    test('health check endpoint responds', async ({ request }) => {
        const response = await request.get('/up');
        expect(response.status()).toBe(200);
    });

    test('signup redirects when public registration is disabled', async ({ page }) => {
        await page.goto('/signup');
        await expect(page).toHaveURL(/signin/);
    });
});
