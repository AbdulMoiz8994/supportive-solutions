import fs from 'fs';
import path from 'path';
import type { Page } from '@playwright/test';

export async function uploadFile(
    page: Page,
    selector: string,
    filePath: string,
): Promise<void> {
    const absolute = path.resolve(filePath);
    if (!fs.existsSync(absolute)) {
        throw new Error(`Upload file not found: ${absolute}`);
    }
    await page.locator(selector).setInputFiles(absolute);
}

export async function downloadFile(
    page: Page,
    trigger: () => Promise<void>,
    downloadDir = 'playwright-results/downloads',
): Promise<string> {
    fs.mkdirSync(downloadDir, { recursive: true });
    const [download] = await Promise.all([
        page.waitForEvent('download', { timeout: 30_000 }),
        trigger(),
    ]);
    const filePath = path.join(downloadDir, download.suggestedFilename());
    await download.saveAs(filePath);
    return filePath;
}

export async function assertDownloadHeaders(filePath: string, expectedExt: string): Promise<void> {
    if (!filePath.endsWith(expectedExt)) {
        throw new Error(`Expected download with extension ${expectedExt}, got ${filePath}`);
    }
    const stat = fs.statSync(filePath);
    if (stat.size === 0) {
        throw new Error(`Downloaded file is empty: ${filePath}`);
    }
}

export function createTempFile(filename: string, content: string | Buffer): string {
    const dir = path.join('playwright-results', 'fixtures');
    fs.mkdirSync(dir, { recursive: true });
    const filePath = path.join(dir, filename);
    fs.writeFileSync(filePath, content);
    return filePath;
}
