import { test as setup } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { login } from './helpers/auth';
import { TEST_USERS, type TestUserKey } from './helpers/config';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const authDir = path.join(__dirname, '.auth');

setup.beforeAll(() => {
    fs.mkdirSync(authDir, { recursive: true });
});

for (const userKey of Object.keys(TEST_USERS) as TestUserKey[]) {
    setup(`authenticate ${userKey}`, async ({ page }) => {
        await login(page, userKey);
        await page.context().storageState({ path: path.join(authDir, `${userKey}.json`) });
    });
}
