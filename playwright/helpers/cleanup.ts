import type { Page } from '@playwright/test';

export async function cleanupCreatedRecords(page: Page, deleteUrls: string[]): Promise<void> {
    for (const url of deleteUrls) {
        try {
            await page.request.delete(url);
        } catch {
            // best-effort cleanup
        }
    }
}
