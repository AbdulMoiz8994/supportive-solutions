import { test, expect } from '../fixtures/app.fixture';
import { assertPageLoads, assertExportEndpoint } from '../helpers/module';

test.describe('Communications Hub', () => {
    test.use({ userKey: 'staff' });

    test('communications index loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/communications', { bodyPattern: /communication/i });
    });

    test('communications export endpoint', async ({ page, authenticatedPage }) => {
        await assertExportEndpoint(page, '/communications/export');
    });

    test('send request compose page loads', async ({ page, authenticatedPage }) => {
        const response = await page.goto('/communications/send-request');
        expect(response?.status()).toBeLessThan(500);
        await expect(page).not.toHaveURL(/signin/);
        await expect(page.locator('body')).not.toContainText(/access denied/i);
    });

    test('notifications index loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/communications/notifications', { bodyPattern: /notification/i });
    });

    test('notifications unread count endpoint', async ({ page, authenticatedPage }) => {
        const response = await page.request.get('/communications/notifications/unread-count');
        expect(response.status()).toBeLessThan(500);
    });

    test('secure messages index loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/communications/secure-messages', { bodyPattern: /message|secure/i });
    });
});
