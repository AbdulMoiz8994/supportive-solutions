import type { Page } from '@playwright/test';
import { downloadFile, assertDownloadHeaders } from './files';
import { waitForNetworkIdle } from './waits';

export async function triggerExport(
    page: Page,
    buttonPattern: RegExp = /export|download/i,
): Promise<string> {
    const btn = page.getByRole('link', { name: buttonPattern })
        .or(page.getByRole('button', { name: buttonPattern }))
        .first();
    return downloadFile(page, () => btn.click());
}

export async function assertCsvExport(page: Page, trigger: () => Promise<void>): Promise<string> {
    const filePath = await downloadFile(page, trigger);
    await assertDownloadHeaders(filePath, '.csv');
    return filePath;
}

export async function assertPdfExport(page: Page, trigger: () => Promise<void>): Promise<string> {
    const filePath = await downloadFile(page, trigger);
    if (!filePath.endsWith('.pdf')) {
        throw new Error(`Expected PDF export, got ${filePath}`);
    }
    return filePath;
}

export async function importFile(
    page: Page,
    inputSelector: string,
    filePath: string,
    submitPattern: RegExp = /import|upload/i,
): Promise<void> {
    const { uploadFile } = await import('./files');
    await uploadFile(page, inputSelector, filePath);
    await page.getByRole('button', { name: submitPattern }).click();
    await waitForNetworkIdle(page);
}
