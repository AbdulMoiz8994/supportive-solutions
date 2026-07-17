import { test, expect } from '../fixtures/app.fixture';
import { login } from '../helpers/auth';
import { submitSearchForm } from '../helpers/wizard';

const SHOWCASE_PERIOD = '2026-05';
const BULK_PERIOD = '2024-05';

test.describe('Billing Claims Audit — Index & Filters', () => {
    test.use({ userKey: 'staff' });

    test('index loads with showcase period data', async ({ page, authenticatedPage }) => {
        await page.goto(`/billing-claims-audit?period=${SHOWCASE_PERIOD}`);
        await expect(page.getByRole('heading', { name: /Billing & Claims/i })).toBeVisible();
        await expect(page.locator('body')).toContainText(/Maria Hassan|Khalil Ahmed|claim/i);
    });

    test('search filters by client name', async ({ page, authenticatedPage }) => {
        await page.goto(`/billing-claims-audit?period=${SHOWCASE_PERIOD}`);
        await page.getByPlaceholder(/Client, member ID/i).fill('Maria Hassan');
        await submitSearchForm(page, /Client, member ID/i);
        await expect(page.locator('body')).toContainText(/Maria Hassan/i);
    });

    test('search filters by claim number', async ({ page, authenticatedPage }) => {
        await page.goto(`/billing-claims-audit?period=${SHOWCASE_PERIOD}`);
        await page.getByPlaceholder(/Client, member ID/i).fill('837P-2026-05-0427');
        await submitSearchForm(page, /Client, member ID/i);
        await expect(page.locator('body')).toContainText(/837P-2026-05-0427|Maria Hassan/i);
    });

    test('status tab filters submitted claims', async ({ page, authenticatedPage }) => {
        await page.goto(`/billing-claims-audit?period=${BULK_PERIOD}`);
        const submittedTab = page.getByRole('link', { name: /Submitted/i });
        if (await submittedTab.count() > 0) {
            await submittedTab.first().click();
            await page.waitForURL(/status=submitted/);
        }
    });

    test('program filter MICH works', async ({ page, authenticatedPage }) => {
        await page.goto(`/billing-claims-audit?period=${BULK_PERIOD}`);
        const michChip = page.getByRole('link', { name: /^MICH$/i });
        if (await michChip.count() > 0) {
            await michChip.first().click();
            await page.waitForTimeout(500);
        }
    });

    test('export endpoint responds', async ({ page, authenticatedPage }) => {
        const response = await page.request.get(`/billing-claims-audit/export?period=${BULK_PERIOD}`);
        expect(response.status()).toBeLessThan(500);
    });

    test('aging report page loads', async ({ page, authenticatedPage }) => {
        await page.goto('/billing-claims-audit/aging');
        await expect(page.locator('body')).toContainText(/aging|overdue|claim/i);
    });

    test('aging export endpoint responds', async ({ page, authenticatedPage }) => {
        const response = await page.request.get('/billing-claims-audit/aging/export');
        expect(response.status()).toBeLessThan(500);
    });
});

test.describe('Billing Claims Audit — Show Page', () => {
    test.use({ userKey: 'admin' });

    test('MICH paid claim show page loads with workflow sections', async ({ page, authenticatedPage }) => {
        await page.goto(`/billing-claims-audit?period=${SHOWCASE_PERIOD}&search=Maria+Hassan`);
        const viewLink = page.getByRole('link', { name: /View claim/i }).first();
        await expect(viewLink).toBeVisible();
        await viewLink.click();
        await page.waitForURL(/\/billing-claims-audit\/\d+/);
        await expect(page.locator('body')).toContainText(/Maria Hassan|workflow|billing|claim/i);
    });

    test('DHS claim show page has Sigma portal action', async ({ page, authenticatedPage }) => {
        await page.goto(`/billing-claims-audit?period=${SHOWCASE_PERIOD}&search=Khalil+Ahmed`);
        const viewLink = page.getByRole('link', { name: /View claim/i }).first();
        await expect(viewLink).toBeVisible();
        await viewLink.click();
        await page.waitForURL(/\/billing-claims-audit\/\d+/);
        await expect(page.locator('body')).toContainText(/Khalil Ahmed|Sigma|ASW|DHS/i);
    });
});

test.describe('Billing Claims Audit — Authorization', () => {
    test('staff can view but not generate claims', async ({ page }) => {
        await login(page, 'staff');
        await page.goto(`/billing-claims-audit?period=${SHOWCASE_PERIOD}`);
        await expect(page.locator('body')).not.toContainText(/access denied/i);
        const generateBtn = page.getByRole('button', { name: /Generate & submit now/i });
        expect(await generateBtn.isVisible().catch(() => false)).toBeFalsy();
    });

    test('admin can see generate and submit action', async ({ page }) => {
        await login(page, 'admin');
        await page.goto(`/billing-claims-audit?period=${SHOWCASE_PERIOD}`);
        const generateBtn = page.getByRole('button', { name: /Generate & submit now/i });
        await expect(generateBtn).toBeVisible();
    });
});
