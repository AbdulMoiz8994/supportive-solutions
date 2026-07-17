import type { Page } from '@playwright/test';
import { waitForNetworkIdle, waitForTableRows } from './waits';

export async function searchTable(page: Page, query: string, inputSelector?: string): Promise<void> {
    const input = inputSelector
        ? page.locator(inputSelector)
        : page.locator('input[type="search"], input[placeholder*="search" i], [data-table-search]').first();
    await input.fill(query);
    await input.press('Enter').catch(() => input.dispatchEvent('input'));
    await waitForNetworkIdle(page);
}

export async function filterTable(page: Page, filterLabel: string, value: string): Promise<void> {
    const select = page.locator(`select[name*="${filterLabel}" i], [data-filter="${filterLabel}"]`).first();
    if (await select.count() > 0) {
        await select.selectOption(value);
    } else {
        const btn = page.getByRole('button', { name: new RegExp(filterLabel, 'i') }).first();
        await btn.click();
        await page.getByRole('option', { name: value }).click();
    }
    await waitForNetworkIdle(page);
}

export async function sortTable(page: Page, columnHeader: string | RegExp): Promise<void> {
    const header = page.locator('table th').filter({ hasText: columnHeader }).first();
    await header.click();
    await waitForNetworkIdle(page);
}

export async function goToNextPage(page: Page): Promise<boolean> {
    const next = page.getByRole('link', { name: /next|›|»/i }).or(
        page.locator('[aria-label="Next page"], .pagination .next'),
    ).first();
    if (!(await next.isVisible().catch(() => false))) {
        return false;
    }
    const disabled = await next.getAttribute('aria-disabled');
    if (disabled === 'true') {
        return false;
    }
    await next.click();
    await waitForNetworkIdle(page);
    return true;
}

export async function resetFilters(page: Page): Promise<void> {
    const reset = page.getByRole('button', { name: /reset|clear filters/i }).first();
    if (await reset.isVisible().catch(() => false)) {
        await reset.click();
        await waitForNetworkIdle(page);
    }
}

export async function assertTableHasRows(page: Page, min = 1): Promise<number> {
    const rows = await waitForTableRows(page, 0);
    const count = await rows.count();
    if (count < min) {
        throw new Error(`Expected at least ${min} rows, found ${count}`);
    }
    return count;
}

export async function assertEmptyState(page: Page, pattern: RegExp = /no (records|results|data)/i): Promise<void> {
    const body = await page.locator('body').innerText();
    if (!pattern.test(body)) {
        throw new Error(`Expected empty state matching ${pattern}`);
    }
}

export async function clickRowAction(page: Page, rowText: string | RegExp, action: string | RegExp): Promise<void> {
    const row = page.locator('table tbody tr').filter({ hasText: rowText }).first();
    await row.getByRole('link', { name: action }).or(row.getByRole('button', { name: action })).first().click();
    await waitForNetworkIdle(page);
}
