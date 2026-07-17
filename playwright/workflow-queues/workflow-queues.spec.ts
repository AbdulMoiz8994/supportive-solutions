import { test } from '../fixtures/app.fixture';
import { assertPageLoads } from '../helpers/module';

test.describe('Workflow Queues Module', () => {
    test.use({ userKey: 'admin' });

    test('workflow queues page loads', async ({ page, authenticatedPage }) => {
        await assertPageLoads(page, '/workflow-queues', { bodyPattern: /queue|workflow|approval/i });
    });
});
