import type { Page } from '@playwright/test';
import { waitForNetworkIdle, waitForToast } from './waits';

export async function fillForm(page: Page, fields: Record<string, string>): Promise<void> {
    for (const [name, value] of Object.entries(fields)) {
        const input = page.locator(`[name="${name}"], #${name}`).first();
        const tag = await input.evaluate((el) => el.tagName.toLowerCase()).catch(() => 'input');
        if (tag === 'select') {
            await input.selectOption(value);
        } else {
            await input.fill(value);
        }
    }
}

export async function submitForm(page: Page, buttonPattern: RegExp = /save|create|submit|update/i): Promise<void> {
    await page.getByRole('button', { name: buttonPattern }).first().click();
    await waitForNetworkIdle(page);
}

export async function createRecord(
    page: Page,
    createPath: string,
    fields: Record<string, string>,
    submitPattern?: RegExp,
): Promise<void> {
    await page.goto(createPath);
    await fillForm(page, fields);
    await submitForm(page, submitPattern);
}

export async function updateRecord(
    page: Page,
    editPath: string,
    fields: Record<string, string>,
): Promise<void> {
    await page.goto(editPath);
    await fillForm(page, fields);
    await submitForm(page, /save|update/i);
}

export async function deleteRecord(page: Page, deleteButton: ReturnType<Page['locator']>): Promise<void> {
    page.once('dialog', (dialog) => dialog.accept());
    await deleteButton.click();
    await waitForNetworkIdle(page);
}

export async function assertValidationErrors(page: Page, patterns: RegExp[]): Promise<void> {
    const body = await page.locator('body').innerText();
    for (const pattern of patterns) {
        if (!pattern.test(body)) {
            throw new Error(`Expected validation error matching ${pattern}`);
        }
    }
}

export async function assertSuccessMessage(page: Page, pattern: RegExp): Promise<void> {
    await waitForToast(page, pattern).catch(async () => {
        const body = await page.locator('body').innerText();
        if (!pattern.test(body)) {
            throw new Error(`Expected success message matching ${pattern}`);
        }
    });
}
