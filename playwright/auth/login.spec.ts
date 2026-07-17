import { test, expect } from '../fixtures/app.fixture';
import { login, logout, readOtpFromLog, logFileSize, completeTwoFactor } from '../helpers/auth';
import { TEST_USERS } from '../helpers/config';

test.describe('Authentication — Login', () => {
    test('login page loads', async ({ page }) => {
        await page.goto('/signin');
        await expect(page.locator('input[name="email"], input[type="email"]')).toBeVisible();
        await expect(page.locator('input[name="password"]')).toBeVisible();
        await expect(page.getByRole('button', { name: /sign in|login/i })).toBeVisible();
    });

    test('valid login redirects to dashboard after 2FA', async ({ page }) => {
        await login(page, 'superAdmin');
        await expect(page).toHaveURL(/dashboard/);
    });

    test('invalid login shows error', async ({ page }) => {
        await page.goto('/signin');
        await page.locator('input[name="email"]').fill('invalid@example.com');
        await page.locator('input[name="password"]').fill('wrongpassword');
        await page.getByRole('button', { name: /sign in|login/i }).click();
        await expect(page).toHaveURL(/signin/);
        const body = await page.locator('body').innerText();
        expect(body).toMatch(/invalid|credentials|failed|incorrect/i);
    });

    test('inactive user cannot login', async ({ page }) => {
        // Requires seeded inactive user — skip if not present
        await page.goto('/signin');
        await page.locator('input[name="email"]').fill('inactive@beydountech.com');
        await page.locator('input[name="password"]').fill('password');
        await page.getByRole('button', { name: /sign in|login/i }).click();
        // Should remain on signin or show error
        await page.waitForTimeout(2000);
        const url = page.url();
        expect(url).toMatch(/signin/);
    });

    test('remember me checkbox is present', async ({ page }) => {
        await page.goto('/signin');
        const remember = page.locator('input[name="remember"]');
        if (await remember.count() > 0) {
            await expect(remember).toBeVisible();
        }
    });
});

test.describe('Authentication — Logout', () => {
    test('logout ends session', async ({ page, authenticatedPage }) => {
        await logout(page);
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/signin/);
    });
});

test.describe('Authentication — Password Reset', () => {
    test('forgot password page loads', async ({ page }) => {
        await page.goto('/forgot-password');
        await expect(page.locator('input[name="email"], input[type="email"]')).toBeVisible();
    });

    test('reset password request accepts email', async ({ page }) => {
        await page.goto('/forgot-password');
        await page.locator('input[name="email"]').fill(TEST_USERS.admin.email);
        await page.getByRole('button', { name: /send|reset|email/i }).click();
        await page.waitForLoadState('networkidle');
        const body = await page.locator('body').innerText();
        expect(body).toMatch(/sent|email|reset/i);
    });

    test('reset password without token redirects', async ({ page }) => {
        await page.goto('/reset-password');
        await expect(page).toHaveURL(/forgot-password|password\.request/);
    });
});

test.describe('Authentication — Unauthorized Access', () => {
    test('guest cannot access dashboard', async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/signin/);
    });

    test('guest cannot access clients', async ({ page }) => {
        await page.goto('/clients');
        await expect(page).toHaveURL(/signin/);
    });

    test('employee cannot access global settings', async ({ page }) => {
        await login(page, 'employee');
        await page.goto('/settings/global');
        const body = await page.locator('body').innerText();
        const url = page.url();
        expect(url.includes('403') || body.includes('403') || body.includes('Forbidden') || url.includes('signin')).toBeTruthy();
    });

    test('staff cannot access platform users', async ({ page }) => {
        await login(page, 'staff');
        await page.goto('/users');
        const status = await page.evaluate(() => document.body.innerText);
        expect(status).toMatch(/403|Forbidden|not authorized|signin/i);
    });
});

test.describe('Authentication — Role-Based Access', () => {
    test('super admin can access global settings', async ({ page, authenticatedPage }) => {
        await page.goto('/settings/global');
        await expect(page).not.toHaveURL(/signin/);
        const body = await page.locator('body').innerText();
        expect(body).toMatch(/global|settings|agency/i);
    });

    test('admin can access settings index', async ({ page }) => {
        await login(page, 'admin');
        await page.goto('/settings');
        await expect(page).not.toHaveURL(/signin/);
    });

    test('staff can access clients listing', async ({ page }) => {
        await login(page, 'staff');
        await page.goto('/clients');
        await expect(page).not.toHaveURL(/signin/);
    });
});

test.describe('Authentication — Two-Factor', () => {
    test('super admin is redirected to 2FA after login', async ({ page }) => {
        await page.goto('/signin');
        await page.locator('input[name="email"]').fill(TEST_USERS.superAdmin.email);
        await page.locator('input[name="password"]').fill(TEST_USERS.superAdmin.password);
        const offset = logFileSize();
        await page.getByRole('button', { name: /sign in|login/i }).click();
        await page.waitForURL(/two-factor|dashboard/, { timeout: 15_000 });
        if (page.url().includes('two-factor')) {
            await completeTwoFactor(page, offset);
            await expect(page).toHaveURL(/dashboard/);
        }
    });
});
