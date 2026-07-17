import { test } from '../fixtures/app.fixture';
import { assertPageLoads, assertForbidden } from '../helpers/module';
import { login } from '../helpers/auth';

test.describe('Locations Module', () => {
    test.use({ userKey: 'superAdmin' });

    test('locations listing loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/locations', { bodyPattern: /location/i });
    });
});

test.describe('Locations — Authorization', () => {
    test('admin cannot access locations management', async ({ page }) => {
        await login(page, 'admin');
        await assertForbidden(page, '/locations');
    });
});
