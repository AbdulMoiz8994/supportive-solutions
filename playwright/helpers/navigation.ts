import type { Page } from '@playwright/test';
import { waitForNetworkIdle } from './waits';

export async function navigateTo(page: Page, path: string): Promise<void> {
    await page.goto(path);
    await waitForNetworkIdle(page);
}

export async function clickSidebarLink(page: Page, label: string | RegExp): Promise<void> {
    const link = page.locator('nav a, aside a, [data-sidebar] a').filter({ hasText: label }).first();
    await link.click();
    await waitForNetworkIdle(page);
}

export async function assertCurrentPath(page: Page, pattern: RegExp | string): Promise<void> {
    const url = new URL(page.url());
    const path = url.pathname;
    if (typeof pattern === 'string') {
        if (!path.includes(pattern)) {
            throw new Error(`Expected path containing "${pattern}", got "${path}"`);
        }
    } else if (!pattern.test(path)) {
        throw new Error(`Expected path matching ${pattern}, got "${path}"`);
    }
}

export async function openTab(page: Page, label: string | RegExp): Promise<void> {
    const tab = page.getByRole('tab', { name: label }).or(
        page.locator('button, a').filter({ hasText: label }),
    ).first();
    await tab.click();
    await page.waitForTimeout(300);
}

export async function globalSearch(page: Page, query: string): Promise<void> {
    const input = page.locator('input[type="search"], input[placeholder*="Search" i]').first();
    await input.fill(query);
    await input.press('Enter');
    await waitForNetworkIdle(page);
}
