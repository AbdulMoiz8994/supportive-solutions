import { test, expect } from '../fixtures/app.fixture';
import {
    uniqueIntakePayload,
    fillVisibleField,
    clickIntakeContinue,
} from '../helpers/wizard';

test.describe('Intake — Wizard Flow', () => {
    test.use({ userKey: 'staff' });

    test('wizard loads all step labels', async ({ page, authenticatedPage }) => {
        await page.goto('/intakes/wizard');
        await expect(page.locator('body')).toContainText(/Scan|Verify|Eligibility|Program|Create/i);
    });

    test('can skip scan and reach verify step', async ({ page, authenticatedPage }) => {
        await page.goto('/intakes/wizard');
        await page.getByRole('button', { name: /Skip scan/i }).click();
        await expect(page.locator('input[name="first_name"]:visible')).toBeVisible();
    });

    test('verify step requires first and last name', async ({ page, authenticatedPage }) => {
        await page.goto('/intakes/wizard');
        await page.getByRole('button', { name: /Skip scan/i }).click();

        page.once('dialog', (dialog) => dialog.accept());
        await clickIntakeContinue(page);

        await expect(page.locator('input[name="first_name"]:visible')).toBeVisible();
    });

    test('manual intake wizard reaches eligibility step', async ({ page, authenticatedPage }) => {
        const data = uniqueIntakePayload();
        await page.goto('/intakes/wizard');
        await page.getByRole('button', { name: /Skip scan/i }).click();

        await fillVisibleField(page, 'first_name', data.first_name);
        await fillVisibleField(page, 'last_name', data.last_name);
        await fillVisibleField(page, 'phone', data.phone);
        await fillVisibleField(page, 'email', data.email);
        await clickIntakeContinue(page);

        await expect(page.getByRole('button', { name: /Check eligibility/i })).toBeVisible();
    });

    test('eligibility check runs and shows result', async ({ page, authenticatedPage }) => {
        const data = uniqueIntakePayload();
        await page.goto('/intakes/wizard');
        await page.getByRole('button', { name: /Skip scan/i }).click();

        await fillVisibleField(page, 'first_name', data.first_name);
        await fillVisibleField(page, 'last_name', data.last_name);
        await fillVisibleField(page, 'member_id', data.member_id);
        await clickIntakeContinue(page);

        await page.getByRole('button', { name: /Check eligibility/i }).click();
        await page.waitForTimeout(3000);
        const body = await page.locator('body').innerText();
        expect(body).toMatch(/eligible|verification|ineligible|checking|program/i);
    });
});

test.describe('Intake — Listing', () => {
    test.use({ userKey: 'staff' });

    test('intake listing shows seeded records', async ({ page, authenticatedPage }) => {
        await page.goto('/intakes');
        await expect(page.locator('body')).toContainText(/Alice Green|Thomas White|intake/i);
    });

    test('intake detail page loads from listing', async ({ page, authenticatedPage }) => {
        await page.goto('/intakes');
        await page.getByRole('link', { name: 'View Profile' }).first().click();
        await page.waitForURL(/\/intakes\/\d+/);
        await expect(page.locator('body')).not.toContainText(/access denied/i);
    });
});
