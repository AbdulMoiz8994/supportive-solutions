import type { Page } from '@playwright/test';

export async function screenshotOnFailure(page: Page, name: string): Promise<string> {
    const safeName = name.replace(/[^a-z0-9-_]/gi, '-').toLowerCase();
    const path = `playwright-results/screenshots/${safeName}-${Date.now()}.png`;
    await page.screenshot({ path, fullPage: true });
    return path;
}
