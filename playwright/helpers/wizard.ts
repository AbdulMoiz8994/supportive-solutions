import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';
import { waitForNetworkIdle } from './waits';

export function uniqueSuffix(): string {
    return `${Date.now()}${Math.floor(Math.random() * 1000)}`;
}

export function uniqueClientPayload() {
    const suffix = uniqueSuffix();
    return {
        first_name: `E2E`,
        last_name: `Client${suffix}`,
        email: `e2e.client.${suffix}@example.com`,
        phone: '(313) 555-0100',
        member_id: `MD-${String(suffix).slice(-5).padStart(5, '0')}`,
        address: '123 Test Street, Detroit MI 48201',
        county: 'Wayne',
    };
}

export function uniqueIntakePayload() {
    const suffix = uniqueSuffix();
    return {
        first_name: `E2E`,
        last_name: `Intake${suffix}`,
        email: `e2e.intake.${suffix}@example.com`,
        phone: '(313) 555-0200',
        member_id: `MD-${String(suffix).slice(-5).padStart(5, '0')}`,
    };
}

export async function clickWizardNext(page: Page): Promise<void> {
    await page.getByRole('button', { name: /Next/i }).first().click();
    await page.waitForTimeout(400);
}

export async function clickIntakeContinue(page: Page): Promise<void> {
    await page.getByRole('button', { name: /Continue/i }).click();
    await page.waitForTimeout(400);
}

export async function submitSearchForm(page: Page, placeholder: RegExp | string): Promise<void> {
    const input = page.getByPlaceholder(placeholder);
    await input.press('Enter');
    await page.waitForLoadState('networkidle');
}

export async function clickWizardPrevious(page: Page): Promise<void> {
    await page.getByRole('button', { name: /Previous/i }).click();
    await page.waitForTimeout(300);
}

export async function fillVisibleField(page: Page, name: string, value: string): Promise<void> {
    const field = page.locator(`input[name="${name}"]:visible, select[name="${name}"]:visible, textarea[name="${name}"]:visible`).first();
    await field.waitFor({ state: 'visible', timeout: 10_000 });
    const tag = await field.evaluate((el) => el.tagName.toLowerCase());
    if (tag === 'select') {
        await field.selectOption({ label: value }).catch(() => field.selectOption(value));
    } else {
        await field.fill(value);
    }
}

export async function selectFirstCoverageType(page: Page): Promise<void> {
    const select = page.locator('select[name="coverage_type_id"]:visible').first();
    await select.waitFor({ state: 'visible' });
    const options = await select.locator('option').all();
    for (const opt of options) {
        const value = await opt.getAttribute('value');
        if (value && value !== '') {
            await select.selectOption(value);
            return;
        }
    }
}

export async function assertOnClientShowPage(page: Page, lastName: string): Promise<void> {
    await page.waitForURL(/\/clients\/\d+/);
    await expect(page.locator('body')).toContainText(new RegExp(lastName, 'i'));
}

export async function waitForSuccessRedirect(page: Page, urlPattern: RegExp): Promise<void> {
    await page.waitForURL(urlPattern, { timeout: 30_000 });
    await waitForNetworkIdle(page);
}
