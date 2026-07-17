import type { Page, Locator } from '@playwright/test';

export async function waitForNetworkIdle(page: Page, timeout = 5000): Promise<void> {
    await page.waitForLoadState('networkidle', { timeout }).catch(() => {});
}

export async function waitForToast(page: Page, pattern?: RegExp): Promise<void> {
    const toast = page.locator('#toast-container .toast, .toastr, [role="alert"]').first();
    await toast.waitFor({ state: 'visible', timeout: 10_000 }).catch(() => {});
    if (pattern) {
        await toast.filter({ hasText: pattern }).waitFor({ state: 'visible', timeout: 5000 });
    }
}

export async function waitForPageHeading(page: Page, pattern: RegExp): Promise<void> {
    await page.getByRole('heading').filter({ hasText: pattern }).first().waitFor({ state: 'visible' });
}

export async function waitForTableRows(page: Page, minRows = 0): Promise<Locator> {
    const rows = page.locator('table tbody tr');
    await rows.first().waitFor({ state: 'visible', timeout: 15_000 }).catch(() => {});
    const count = await rows.count();
    if (count < minRows) {
        throw new Error(`Expected at least ${minRows} table rows, found ${count}`);
    }
    return rows;
}

export async function waitForModal(page: Page): Promise<Locator> {
    const modal = page.locator('[role="dialog"], .modal, [x-show]').filter({ has: page.locator('form, button') }).first();
    await modal.waitFor({ state: 'visible', timeout: 10_000 });
    return modal;
}

export async function waitForSpinnerGone(page: Page): Promise<void> {
    const spinner = page.locator('.animate-spin, [aria-busy="true"], .loading');
    await spinner.first().waitFor({ state: 'hidden', timeout: 15_000 }).catch(() => {});
}
