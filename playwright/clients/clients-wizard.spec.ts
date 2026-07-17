import { test, expect } from '../fixtures/app.fixture';
import {
    uniqueClientPayload,
    clickWizardNext,
    fillVisibleField,
    selectFirstCoverageType,
    assertOnClientShowPage,
} from '../helpers/wizard';

test.describe('Clients — Create Wizard', () => {
    test.use({ userKey: 'admin' });

    test('wizard navigates through all 6 steps', async ({ page, authenticatedPage }) => {
        const data = uniqueClientPayload();
        await page.goto('/clients/create');
        await expect(page.locator('#clientForm')).toBeVisible();

        await expect(page.getByRole('button', { name: /Basic Information/i })).toBeVisible();

        await fillVisibleField(page, 'first_name', data.first_name);
        await fillVisibleField(page, 'last_name', data.last_name);
        await clickWizardNext(page);
        await expect(page.locator('input[name="phone"]:visible')).toBeVisible();

        await fillVisibleField(page, 'phone', data.phone);
        await clickWizardNext(page);
        await selectFirstCoverageType(page);
        await clickWizardNext(page);
        await clickWizardNext(page);
        await clickWizardNext(page);

        await expect(page.getByRole('button', { name: 'Enrol Client' })).toBeVisible();
        await expect(page.locator('body')).toContainText(/review|enrol/i);
    });

    test('step 1 validation blocks advance without required fields', async ({ page, authenticatedPage }) => {
        await page.goto('/clients/create');
        await clickWizardNext(page);
        await expect(page.locator('body')).toContainText(/first name is required|last name is required/i);
        await expect(page.locator('input[name="first_name"]:visible')).toBeVisible();
    });

    test('step 3 validation blocks advance without coverage type', async ({ page, authenticatedPage }) => {
        const data = uniqueClientPayload();
        await page.goto('/clients/create');

        await fillVisibleField(page, 'first_name', data.first_name);
        await fillVisibleField(page, 'last_name', data.last_name);
        await clickWizardNext(page);

        await fillVisibleField(page, 'phone', data.phone);
        await clickWizardNext(page);

        await clickWizardNext(page);
        await expect(page.locator('body')).toContainText(/coverage.*required/i);
    });

    test('admin can create client through full wizard', async ({ page, authenticatedPage }) => {
        const data = uniqueClientPayload();
        await page.goto('/clients/create');

        await fillVisibleField(page, 'first_name', data.first_name);
        await fillVisibleField(page, 'last_name', data.last_name);
        await fillVisibleField(page, 'member_id', data.member_id);
        await clickWizardNext(page);

        await fillVisibleField(page, 'phone', data.phone);
        await fillVisibleField(page, 'email', data.email);
        await fillVisibleField(page, 'address', data.address);
        await fillVisibleField(page, 'county', data.county);
        await clickWizardNext(page);

        await selectFirstCoverageType(page);
        await clickWizardNext(page);
        await clickWizardNext(page);
        await clickWizardNext(page);

        await page.getByRole('button', { name: 'Enrol Client' }).click();
        await assertOnClientShowPage(page, data.last_name);
    });
});
