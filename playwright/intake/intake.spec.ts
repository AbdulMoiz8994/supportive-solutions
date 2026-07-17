import { test } from '../fixtures/app.fixture';
import { assertPageLoads, assertGetOk } from '../helpers/module';

test.describe('Intake Module', () => {
    test.use({ userKey: 'staff' });

    test('intake listing loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/intakes', { bodyPattern: /intake/i });
    });

    test('intake wizard loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/intakes/wizard', { bodyPattern: /intake|wizard|eligibility/i });
    });

    test('intake detail from listing', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/intakes');
        const link = page.locator('table tbody tr a, a[href*="/intakes/"]').first();
        if (await link.count() > 0) {
            await link.click();
            await assertPageLoads(page, page.url());
        }
    });
});
