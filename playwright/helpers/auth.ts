import fs from 'fs';
import { execSync } from 'child_process';
import type { Page } from '@playwright/test';
import { APP_ROOT, LOG_FILE, TEST_USERS, type TestUserKey } from './config';

export function readOtpFromLog(sinceByte = 0): string | null {
    try {
        const content = fs.readFileSync(LOG_FILE, 'utf8').slice(sinceByte);
        const patterns = [
            /verification code is:\s*\*?\*?(\d{6})\*?\*?/gi,
            /Your verification code is:\s*\*?\*?(\d{6})\*?\*?/gi,
            /debug_otp["\s:]+(\d{6})/gi,
        ];
        for (const pattern of patterns) {
            const matches = [...content.matchAll(pattern)];
            if (matches.length > 0) {
                return matches[matches.length - 1][1];
            }
        }
    } catch {
        // log file may not exist yet
    }
    return null;
}

export function logFileSize(): number {
    try {
        return fs.statSync(LOG_FILE).size;
    } catch {
        return 0;
    }
}

function clearRateLimitCache(): void {
    try {
        execSync('php artisan cache:clear', { cwd: APP_ROOT, stdio: 'pipe' });
    } catch {
        // best effort
    }
}

export async function getCsrfToken(page: Page): Promise<string> {
    return page.evaluate(() => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta?.getAttribute('content') ?? '';
    });
}

async function readOtpFromPage(page: Page): Promise<string | null> {
    const debugBox = page.locator('.font-mono.text-lg.tracking-widest, [class*="debug"] .font-mono').first();
    if (await debugBox.isVisible().catch(() => false)) {
        const text = (await debugBox.textContent())?.replace(/\s/g, '').trim();
        if (text && /^\d{6}$/.test(text)) {
            return text;
        }
    }
    return null;
}

async function fillOtpInputs(page: Page, otp: string): Promise<void> {
    const digitBoxes = page.locator('input.otp-input');
    const count = await digitBoxes.count();
    if (count === 6) {
        for (let i = 0; i < 6; i++) {
            await digitBoxes.nth(i).fill(otp[i]);
        }
    } else {
        const hidden = page.locator('#full-otp, input[name="otp"]').first();
        if (await hidden.count() > 0) {
            await hidden.evaluate((el: HTMLInputElement, code: string) => {
                el.value = code;
            }, otp);
        }
    }
}

export async function completeTwoFactor(page: Page, logOffset = 0): Promise<void> {
    if (!page.url().includes('two-factor') && !page.url().includes('two-step')) {
        return;
    }

    if (page.url().includes('two-factor/choice')) {
        const emailOpt = page.locator('input[value="email"]').first();
        if (await emailOpt.isVisible().catch(() => false)) {
            await emailOpt.click();
        }
        logOffset = logFileSize();
        await page.locator('button[type="submit"]').first().click();
        await page.waitForLoadState('networkidle');

        if (page.url().includes('two-factor/choice')) {
            clearRateLimitCache();
            await page.reload();
            await page.locator('button[type="submit"]').first().click();
            await page.waitForLoadState('networkidle');
        }
    }

    if (!page.url().includes('two-factor/verify') && !page.url().includes('two-step')) {
        await page.goto('/two-factor/verify');
        await page.waitForLoadState('networkidle');
    }

    let otp = await readOtpFromPage(page);
    if (!otp) {
        otp = readOtpFromLog(logOffset);
    }

    for (let attempt = 0; attempt < 5 && !otp; attempt++) {
        await page.waitForTimeout(1000);
        otp = (await readOtpFromPage(page)) ?? readOtpFromLog(logOffset);
    }

    if (!otp) {
        throw new Error(
            'Could not read 2FA OTP — ensure APP_DEBUG=true (debug box) or MAIL_MAILER=log',
        );
    }

    await fillOtpInputs(page, otp);

    await page.waitForURL((url) => !url.pathname.includes('two-factor') && !url.pathname.includes('two-step'), {
        timeout: 30_000,
    }).catch(async () => {
        if (!page.url().includes('two-factor') && !page.url().includes('two-step')) {
            return;
        }
        const submitBtn = page.getByRole('button', { name: /verify/i }).first();
        if (await submitBtn.isVisible().catch(() => false)) {
            await submitBtn.click();
        }
        await page.waitForURL((url) => !url.pathname.includes('two-factor') && !url.pathname.includes('two-step'), {
            timeout: 30_000,
        });
    });
}

export async function login(
    page: Page,
    userKey: TestUserKey = 'superAdmin',
    options: { remember?: boolean; skipTwoFactor?: boolean } = {},
): Promise<void> {
    const user = TEST_USERS[userKey];
    clearRateLimitCache();
    const logOffset = logFileSize();

    await page.goto('/signin');
    await page.waitForLoadState('domcontentloaded');

    await page.locator('input[name="email"], input[type="email"]').fill(user.email);
    await page.locator('input[name="password"], input[type="password"]').fill(user.password);

    if (options.remember) {
        const remember = page.locator('input[name="remember"], input[type="checkbox"]').first();
        if (await remember.isVisible().catch(() => false)) {
            await remember.check();
        }
    }

    await page.getByRole('button', { name: /sign in|login/i }).click();
    await page.waitForLoadState('networkidle');

    if (!options.skipTwoFactor && (page.url().includes('two-factor') || page.url().includes('two-step'))) {
        await completeTwoFactor(page, logOffset);
    }

    await page.waitForLoadState('networkidle').catch(() => {});
}

export async function logout(page: Page): Promise<void> {
    const logoutLink = page.locator('form[action*="logout"] button, a[href*="logout"]').first();
    if (await logoutLink.isVisible().catch(() => false)) {
        await logoutLink.click();
    } else {
        await page.request.post('/logout', {
            headers: { 'X-CSRF-TOKEN': await getCsrfToken(page) },
        });
    }
    await page.waitForURL(/signin|\/$/, { timeout: 15_000 }).catch(() => {});
}

export async function assertUnauthorizedRedirect(page: Page, path: string): Promise<void> {
    await page.goto(path);
    await page.waitForLoadState('domcontentloaded');
    const url = page.url();
    if (!url.includes('signin') && !url.includes('403')) {
        throw new Error(`Expected unauthorized redirect from ${path}, got ${url}`);
    }
}
