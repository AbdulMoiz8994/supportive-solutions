import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export const APP_ROOT = path.resolve(__dirname, '../..');
export const LOG_FILE = path.join(APP_ROOT, 'storage/logs/laravel.log');

export const TEST_USERS = {
    superAdmin: { email: 'super@beydountech.com', password: 'super123', role: 'Super Administrator' },
    admin: { email: 'admin@beydountech.com', password: 'admin123', role: 'Administrator' },
    staff: { email: 'staff@beydountech.com', password: 'staff123', role: 'Operations Staff' },
    employee: { email: 'caregiver@beydountech.com', password: 'care123', role: 'Employee' },
} as const;

export type TestUserKey = keyof typeof TEST_USERS;
