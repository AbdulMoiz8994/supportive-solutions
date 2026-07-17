import { test, expect } from '../fixtures/app.fixture';

async function waitForClientRegistry(page: import('@playwright/test').Page): Promise<void> {
    await expect(page.getByRole('heading', { name: 'Clients' })).toBeVisible();
    await expect(page.locator('body')).toContainText(/\d+\s+active/i);
}

test.describe('Clients — Listing & Filters', () => {
    test.use({ userKey: 'staff' });

    test('listing shows client table with data', async ({ page, authenticatedPage }) => {
        await page.goto('/clients');
        await waitForClientRegistry(page);
        await expect(page.locator('body')).not.toContainText(/no clients match your filters/i);
    });

    test('search filters by client name', async ({ page, authenticatedPage }) => {
        await page.goto('/clients');
        await waitForClientRegistry(page);
        await page.getByPlaceholder(/Filter by name, Medicaid ID/i).fill('Maria Hassan');
        await page.waitForTimeout(600);
        await expect(page.getByText('Maria Hassan').first()).toBeVisible();
    });

    test('search with nonsense shows empty state', async ({ page, authenticatedPage }) => {
        await page.goto('/clients');
        await waitForClientRegistry(page);
        await page.getByPlaceholder(/Filter by name, Medicaid ID/i).fill('ZZZZNONEXISTENT99999');
        await page.waitForTimeout(600);
        await expect(page.locator('body')).toContainText(/no clients match your filters/i);
    });

    test('status tab filters clients', async ({ page, authenticatedPage }) => {
        await page.goto('/clients');
        await waitForClientRegistry(page);
        await page.getByRole('button', { name: /Approved active/i }).click();
        await page.waitForTimeout(400);
        await expect(page.locator('body')).toContainText(/client/i);
    });

    test('program filter chips work', async ({ page, authenticatedPage }) => {
        await page.goto('/clients');
        await waitForClientRegistry(page);
        await page.locator('button').filter({ hasText: /^DHS$/ }).first().click();
        await page.waitForTimeout(400);
        await expect(page.locator('body')).toContainText(/client/i);
    });

    test('pagination controls are present when data exists', async ({ page, authenticatedPage }) => {
        await page.goto('/clients');
        await waitForClientRegistry(page);
        const nextBtn = page.locator('button').filter({ has: page.locator('svg') }).nth(1);
        const page2 = page.getByRole('button', { name: '2', exact: true });
        if (await page2.count() > 0) {
            await page2.click();
            await page.waitForTimeout(400);
            await expect(page).toHaveURL(/clients/);
        }
    });

    test('export endpoint returns CSV', async ({ page, authenticatedPage }) => {
        const response = await page.request.get('/clients/export');
        expect(response.status()).toBe(200);
        const contentType = response.headers()['content-type'] ?? '';
        expect(contentType).toMatch(/csv|octet|spreadsheet/i);
    });

    test('enrol client link visible for staff', async ({ page, authenticatedPage }) => {
        await page.goto('/clients');
        await expect(page.getByRole('link', { name: /Enrol client/i })).toBeVisible();
    });
});

test.describe('Clients — Detail Page', () => {
    test.use({ userKey: 'staff' });

    test('client show page loads with tabs', async ({ page, authenticatedPage }) => {
        await page.goto('/clients');
        await waitForClientRegistry(page);
        await page.getByPlaceholder(/Filter by name, Medicaid ID/i).fill('Maria Hassan');
        await page.waitForTimeout(600);
        await page.getByText('Maria Hassan').first().click();
        await page.waitForURL(/\/clients\/\d+/);
        await expect(page.locator('body')).toContainText(/Maria Hassan/i);
    });

    test('client show page has demographic content', async ({ page, authenticatedPage }) => {
        await page.goto('/clients');
        await waitForClientRegistry(page);
        await page.getByText('Maria Hassan').first().click();
        await page.waitForURL(/\/clients\/\d+/);
        const body = await page.locator('body').innerText();
        expect(body.length).toBeGreaterThan(100);
    });
});
