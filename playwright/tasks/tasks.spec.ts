import { test, expect } from '../fixtures/app.fixture';
import { assertPageLoads } from '../helpers/module';

test.describe('Tasks Module', () => {
    test.use({ userKey: 'staff' });

    test('tasks board loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/tasks', { bodyPattern: /task/i });
    });

    test('tasks listing endpoint responds', async ({ page, authenticatedPage }) => {
        const response = await page.request.get('/tasks');
        expect(response.status()).toBeLessThan(500);
    });
});
