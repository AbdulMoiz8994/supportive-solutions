import { test, expect } from '../fixtures/app.fixture';
import { assertPageLoads } from '../helpers/module';

test.describe('Location Switch', () => {
    test.use({ userKey: 'staff' });

    test('location switch endpoint accepts POST', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/dashboard');
        const token = await page.evaluate(() => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '');
        const response = await page.request.post('/location/switch', {
            headers: { 'X-CSRF-TOKEN': token },
            form: { location_id: '1' },
        });
        expect(response.status()).toBeLessThan(500);
    });
});
