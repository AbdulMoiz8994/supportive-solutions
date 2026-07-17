import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';
import { waitForNetworkIdle } from './waits';

export async function gotoModule(page: Page, path: string) {
    const response = await page.goto(path);
    await waitForNetworkIdle(page);
    return response;
}

export async function assertPageLoads(
    page: Page,
    path: string,
    options: { bodyPattern?: RegExp; maxStatus?: number } = {},
): Promise<void> {
    const { bodyPattern, maxStatus = 399 } = options;
    const response = await gotoModule(page, path);
    expect(response?.status() ?? 200).toBeLessThanOrEqual(maxStatus);
    await expect(page).not.toHaveURL(/signin/);
    await expect(page.locator('body')).not.toContainText(/access denied/i);
    if (bodyPattern) {
        await expect(page.locator('body')).toContainText(bodyPattern);
    }
}

export async function assertForbidden(page: Page, path: string): Promise<void> {
    const response = await gotoModule(page, path);
    expect(response?.status()).toBe(403);
    await expect(page.locator('body')).toContainText(/access denied/i);
}

export async function assertGuestRedirect(page: Page, path: string): Promise<void> {
    await page.goto(path);
    await expect(page).toHaveURL(/signin/);
}

export async function assertGetOk(page: Page, path: string, maxStatus = 399): Promise<void> {
    const response = await page.request.get(path);
    expect(response.status()).toBeLessThanOrEqual(maxStatus);
}

export async function assertExportEndpoint(page: Page, path: string): Promise<void> {
    const response = await page.request.get(path);
    expect(response.status()).toBeLessThan(500);
    expect([200, 302, 403]).toContain(response.status());
}

export async function getFirstDetailLink(page: Page, path: string): Promise<string | null> {
    await gotoModule(page, path);
    const link = page.locator('table tbody tr a[href], [data-detail-link]').first();
    if (await link.count() === 0) {
        return null;
    }
    return link.getAttribute('href');
}

export async function assertCreatePageLoads(page: Page, path: string): Promise<void> {
    await assertPageLoads(page, path);
}

export async function assertPlaceholderPage(page: Page, path: string, title: string): Promise<void> {
    await assertPageLoads(page, path, { bodyPattern: new RegExp(title, 'i') });
}
