import { execSync } from 'child_process';
import { APP_ROOT } from './config';

export function artisan(command: string): string {
    return execSync(`php artisan ${command}`, {
        cwd: APP_ROOT,
        encoding: 'utf8',
        stdio: ['pipe', 'pipe', 'pipe'],
    });
}

export function seedE2eDatabase(): void {
    artisan('migrate:fresh --seed --seeder=E2eTestSeeder --force');
}

export function clearCaches(): void {
    artisan('cache:clear');
    artisan('config:clear');
}

export function createTestClientViaArtisan(orgId: number, attrs: Record<string, string> = {}): string {
    const payload = JSON.stringify({ organization_id: orgId, ...attrs });
    return artisan(`tinker --execute="echo json_encode(\\App\\Models\\Client::withoutGlobalScopes()->create(json_decode('${payload}', true))->id);"`);
}
