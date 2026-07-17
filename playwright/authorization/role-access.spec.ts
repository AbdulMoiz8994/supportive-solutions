import { test } from '../fixtures/app.fixture';
import { login } from '../helpers/auth';
import { assertForbidden, assertPageLoads } from '../helpers/module';

const OFFICE_TEAM_ONLY = [
    { path: '/dashboard', label: 'dashboard' },
    { path: '/caregivers', label: 'caregivers' },
    { path: '/intakes', label: 'intakes' },
    { path: '/payroll', label: 'payroll' },
    { path: '/staff', label: 'staff listing' },
    { path: '/billing-claims-audit', label: 'billing claims audit' },
    { path: '/reports', label: 'reports' },
    { path: '/tasks', label: 'tasks' },
    { path: '/forms', label: 'forms' },
];

const EMPLOYEE_ALLOWED = [
    { path: '/schedule', label: 'schedule' },
    { path: '/schedule/board', label: 'schedule board' },
    { path: '/messages', label: 'messages' },
    { path: '/profile', label: 'profile' },
    { path: '/calendar', label: 'calendar' },
];

test.describe('Cross-Module Role Authorization', () => {
    for (const route of OFFICE_TEAM_ONLY) {
        test(`employee blocked from ${route.label}`, async ({ page }) => {
            await login(page, 'employee');
            await assertForbidden(page, route.path);
        });
    }

    for (const route of EMPLOYEE_ALLOWED) {
        test(`employee can access ${route.label}`, async ({ page }) => {
            await login(page, 'employee');
            await assertPageLoads(page, route.path);
        });
    }
});
