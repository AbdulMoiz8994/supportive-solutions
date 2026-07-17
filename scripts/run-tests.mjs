#!/usr/bin/env node
/**
 * Unified test runner — starts Laravel if needed, runs Playwright + PHPUnit/Pest, produces consolidated report.
 *
 * Usage: npm run test
 */
import { spawn, execSync } from 'child_process';
import fs from 'fs';
import http from 'http';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const BASE_URL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';
const PORT = new URL(BASE_URL).port || '8000';

let serverProcess = null;

function log(msg) {
    console.log(`\n▶ ${msg}`);
}

function run(command, args = [], options = {}) {
    return new Promise((resolve, reject) => {
        const child = spawn(command, args, {
            cwd: ROOT,
            stdio: 'inherit',
            shell: process.platform === 'win32',
            ...options,
        });
        child.on('close', (code) => (code === 0 ? resolve() : reject(new Error(`${command} exited ${code}`))));
    });
}

function isServerUp() {
    return new Promise((resolve) => {
        const req = http.get(`${BASE_URL}/up`, (res) => {
            resolve(res.statusCode === 200);
        });
        req.on('error', () => resolve(false));
        req.setTimeout(3000, () => {
            req.destroy();
            resolve(false);
        });
    });
}

async function ensureServer() {
    if (await isServerUp()) {
        log(`Laravel server already running at ${BASE_URL}`);
        return;
    }

    log(`Starting Laravel server on port ${PORT}...`);
    serverProcess = spawn('php', ['artisan', 'serve', '--port', PORT, '--no-reload'], {
        cwd: ROOT,
        stdio: 'pipe',
        shell: process.platform === 'win32',
        detached: false,
    });

    for (let i = 0; i < 30; i++) {
        await new Promise((r) => setTimeout(r, 1000));
        if (await isServerUp()) {
            log('Laravel server is ready');
            return;
        }
    }
    throw new Error('Laravel server failed to start within 30 seconds');
}

function prepareDatabase() {
    log('Preparing test database...');
    try {
        execSync('php artisan migrate:fresh --seed --seeder=E2eTestSeeder --force', {
            cwd: ROOT,
            stdio: 'inherit',
            env: { ...process.env, APP_ENV: process.env.APP_ENV ?? 'local' },
        });
    } catch (e) {
        console.warn('Database seed skipped or failed — ensure DB is configured:', e.message);
    }
}

function writeConsolidatedReport(phpExit, e2eExit) {
    const dir = path.join(ROOT, 'test-reports');
    fs.mkdirSync(dir, { recursive: true });

    let playwrightSummary = { summary: { passed: 0, failed: 0, total: 0 } };
    const pwPath = path.join(dir, 'playwright-summary.json');
    if (fs.existsSync(pwPath)) {
        playwrightSummary = JSON.parse(fs.readFileSync(pwPath, 'utf8'));
    }

    const report = {
        generatedAt: new Date().toISOString(),
        php: { exitCode: phpExit, status: phpExit === 0 ? 'passed' : 'failed' },
        playwright: {
            exitCode: e2eExit,
            status: e2eExit === 0 ? 'passed' : 'failed',
            ...playwrightSummary.summary,
        },
        overall: phpExit === 0 && e2eExit === 0 ? 'passed' : 'failed',
    };

    fs.writeFileSync(path.join(dir, 'consolidated-report.json'), JSON.stringify(report, null, 2));

    const md = [
        '# Consolidated Test Report',
        '',
        `Generated: ${report.generatedAt}`,
        '',
        '## Backend (PHPUnit/Pest)',
        `- Status: **${report.php.status}**`,
        '',
        '## Frontend (Playwright E2E)',
        `- Status: **${report.playwright.status}**`,
        `- Passed: ${playwrightSummary.summary?.passed ?? 'N/A'}`,
        `- Failed: ${playwrightSummary.summary?.failed ?? 'N/A'}`,
        '',
        '## Overall',
        `- **${report.overall.toUpperCase()}**`,
        '',
        '### Artifacts',
        '- `playwright-report/` — HTML report',
        '- `playwright-results/` — screenshots, videos, traces',
        '- `test-reports/consolidated-report.json`',
    ].join('\n');

    fs.writeFileSync(path.join(dir, 'consolidated-report.md'), md);
    log(`Consolidated report written to test-reports/consolidated-report.md`);
}

async function main() {
    const skipPhp = process.argv.includes('--e2e-only');
    const skipE2e = process.argv.includes('--php-only');
    const skipSeed = process.argv.includes('--no-seed');

    let phpExit = 0;
    let e2eExit = 0;

    try {
        if (!skipSeed) {
            prepareDatabase();
        }

        await ensureServer();

        if (!skipPhp) {
            log('Running Laravel Feature + Unit tests...');
            try {
                await run('php', ['artisan', 'config:clear']);
                await run('php', ['artisan', 'test']);
            } catch {
                phpExit = 1;
            }
        }

        if (!skipE2e) {
            log('Running Playwright E2E tests (Chromium only for unified run)...');
            process.env.PLAYWRIGHT_BASE_URL = BASE_URL;
            try {
                await run('npx', ['playwright', 'test', '--project=chromium']);
            } catch {
                e2eExit = 1;
            }
        }

        writeConsolidatedReport(phpExit, e2eExit);

        if (phpExit !== 0 || e2eExit !== 0) {
            process.exit(1);
        }
    } finally {
        if (serverProcess) {
            serverProcess.kill();
        }
    }
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
