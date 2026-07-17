import { test } from '../fixtures/app.fixture';
import { assertPageLoads } from '../helpers/module';

test.describe('eFax Module', () => {
    test.use({ userKey: 'staff' });

    test('efax compose page loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/efax', { bodyPattern: /fax/i });
    });
});
