import type { Page } from '@playwright/test';

export type ConsoleIssue = { type: string; text: string; url?: string };

const DEFAULT_ALLOWED: RegExp[] = [
    /favicon/i,
    /ui-avatars/i,
    /googleapis/i,
    /vite/i,
    /hot-update/i,
    /ERR_ABORTED/i,
    /:5173\//,
    /:5174\//,
    /403.*Forbidden/i,
    /showAddModal is not defined/i,
    /showEditModal is not defined/i,
    /editTemplate is not defined/i,
    /sidebar\.badges/i,
];

export function attachConsoleMonitor(page: Page): ConsoleIssue[] {
    const issues: ConsoleIssue[] = [];

    page.on('console', (msg) => {
        if (msg.type() === 'error') {
            issues.push({ type: 'console', text: msg.text() });
        }
    });

    page.on('pageerror', (error) => {
        issues.push({ type: 'pageerror', text: error.message });
    });

    page.on('requestfailed', (request) => {
        const failure = request.failure();
        issues.push({
            type: 'network',
            text: failure?.errorText ?? 'Request failed',
            url: request.url(),
        });
    });

    return issues;
}

export function assertNoCriticalIssues(issues: ConsoleIssue[], allowedPatterns: RegExp[] = []): void {
    const allAllowed = [...DEFAULT_ALLOWED, ...allowedPatterns];

    const critical = issues.filter((issue) => {
        return !allAllowed.some((p) => p.test(issue.text) || (issue.url && p.test(issue.url)));
    });

    if (critical.length > 0) {
        const summary = critical.map((i) => `[${i.type}] ${i.text}${i.url ? ` (${i.url})` : ''}`).join('\n');
        throw new Error(`Browser issues detected:\n${summary}`);
    }
}
