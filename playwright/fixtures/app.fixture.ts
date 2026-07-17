import path from 'path';
import { fileURLToPath } from 'url';
import fs from 'fs';
import { test as base } from '@playwright/test';
import { login } from '../helpers/auth';
import type { TestUserKey } from '../helpers/config';
import { attachConsoleMonitor, assertNoCriticalIssues } from '../helpers/console';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const authDir = path.join(__dirname, '.auth');

type AppFixtures = {
    authenticatedPage: void;
    userKey: TestUserKey;
    consoleIssues: ReturnType<typeof attachConsoleMonitor>;
};

export const test = base.extend<AppFixtures>({
    userKey: ['superAdmin', { option: true }],

    storageState: async ({ userKey }, use) => {
        const authFile = path.join(authDir, `${userKey}.json`);
        if (fs.existsSync(authFile)) {
            await use(authFile);
        } else {
            await use(undefined);
        }
    },

    consoleIssues: async ({ page }, use) => {
        const issues = attachConsoleMonitor(page);
        await use(issues);
    },

    authenticatedPage: async ({ page, userKey, consoleIssues }, use) => {
        await page.goto('/dashboard');
        if (page.url().includes('signin') || page.url().includes('two-factor') || page.url().includes('two-step')) {
            await login(page, userKey);
        }
        await use();
        assertNoCriticalIssues(consoleIssues);
    },
});

export { expect } from '@playwright/test';
