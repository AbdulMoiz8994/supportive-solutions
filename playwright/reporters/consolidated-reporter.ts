import type { FullConfig, Reporter, TestCase, TestResult } from '@playwright/test/reporter';
import fs from 'fs';
import path from 'path';

class ConsolidatedReporter implements Reporter {
    private results: Array<{ title: string; status: string; project: string; duration: number }> = [];

    onTestEnd(test: TestCase, result: TestResult): void {
        this.results.push({
            title: test.title,
            status: result.status,
            project: test.parent.project()?.name ?? 'default',
            duration: result.duration,
        });
    }

    onEnd(): void {
        const passed = this.results.filter((r) => r.status === 'passed').length;
        const failed = this.results.filter((r) => r.status === 'failed').length;
        const skipped = this.results.filter((r) => r.status === 'skipped').length;

        const report = {
            generatedAt: new Date().toISOString(),
            summary: { total: this.results.length, passed, failed, skipped },
            tests: this.results,
        };

        const dir = path.resolve('test-reports');
        fs.mkdirSync(dir, { recursive: true });
        fs.writeFileSync(path.join(dir, 'playwright-summary.json'), JSON.stringify(report, null, 2));

        const md = [
            '# Playwright E2E Summary',
            '',
            `Generated: ${report.generatedAt}`,
            '',
            `| Metric | Count |`,
            `|--------|-------|`,
            `| Total | ${report.summary.total} |`,
            `| Passed | ${report.summary.passed} |`,
            `| Failed | ${report.summary.failed} |`,
            `| Skipped | ${report.summary.skipped} |`,
            '',
        ].join('\n');

        fs.writeFileSync(path.join(dir, 'playwright-summary.md'), md);
    }
}

export default ConsolidatedReporter;
