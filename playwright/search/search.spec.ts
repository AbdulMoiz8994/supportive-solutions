import { test, expect } from '../fixtures/app.fixture';

test.describe('Global Search', () => {
    test.use({ userKey: 'staff' });

    test('global search API returns results', async ({ page, authenticatedPage }) => {
        const response = await page.request.get('/search?q=test');
        expect(response.status()).toBeLessThan(500);
    });

    test('global search page loads for authenticated user', async ({ page, authenticatedPage }) => {
        const response = await page.goto('/search?q=client');
        expect(response?.status()).toBeLessThan(500);
        await expect(page).not.toHaveURL(/signin/);
    });
});
