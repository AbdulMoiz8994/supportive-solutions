import { test, expect } from '../fixtures/app.fixture';
import { assertPageLoads } from '../helpers/module';

test.describe('Request Templates Module', () => {
    test.use({ userKey: 'admin' });

    test('request templates listing loads', async ({ page, authenticatedPage }) => {
        const response = await page.goto('/request-templates');
        expect(response?.status()).toBeLessThan(500);
        await expect(page).not.toHaveURL(/signin/);
        await expect(page.locator('body')).not.toContainText(/access denied/i);
    });
});
