import { test } from '../fixtures/app.fixture';
import { assertPageLoads, assertForbidden } from '../helpers/module';
import { login } from '../helpers/auth';

test.describe('Global Settings Module', () => {
    test.use({ userKey: 'superAdmin' });

    test('global settings hub loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/settings/global', { bodyPattern: /settings|global|agency/i });
    });

    test('global settings audit log loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/settings/global/audit-log', { bodyPattern: /audit|log/i });
    });
});

test.describe('Global Settings — Authorization', () => {
    test('admin cannot access global settings', async ({ page }) => {
        await login(page, 'admin');
        await assertForbidden(page, '/settings/global');
    });
});
