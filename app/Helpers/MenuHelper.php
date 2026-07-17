<?php

namespace App\Helpers;

use App\Models\User;
use App\Services\RegistryMetricsService;
use App\Services\WorkflowQueueService;
use Illuminate\Support\Facades\Cache;

class MenuHelper
{
    /**
     * Badge cache TTL in seconds. Counts are rebuilt at most once per minute
     * per organization; approve/resolve actions refresh them immediately via
     * the sidebar badges endpoint (A9), which writes through this cache.
     */
    public const BADGE_CACHE_SECONDS = 60;

    public static function badgeCacheKey(string $badge, ?int $orgId): string
    {
        return 'sidebar.badges.'.$badge.'.'.($orgId ?? 'all');
    }

    /**
     * Drop cached badge counts after a mutation (approve, resolve, activate)
     * so the next sidebar render recomputes. The super-admin "all" keys are
     * cleared too, since an org-level change also moves the global counts.
     */
    public static function forgetBadgeCache(?int $orgId): void
    {
        foreach (['workflow', 'clients'] as $badge) {
            Cache::forget(self::badgeCacheKey($badge, $orgId));
            Cache::forget(self::badgeCacheKey($badge, null));
        }
    }
    private static function filterItems($items)
    {
        $user = auth()->user();
        if (!$user) return [];

        return array_values(array_filter($items, function ($item) use ($user) {
            if ($user->isSuperAdmin()) return true;

            if (isset($item['roles']) && !in_array($user->role, $item['roles'])) {
                return false;
            }

            if (isset($item['permission'])) {
                return $user->hasPermission($item['permission']);
            }

            return true;
        }));
    }

    public static function getLandingRoute()
    {
        foreach (self::getMenuGroups() as $group) {
            if (!empty($group['items'])) {
                $firstItem = reset($group['items']);
                return $firstItem['path'] ?? '/dashboard';
            }
        }

        return '/dashboard';
    }

    /**
     * The navigation model, grouped to match the BeydounTech design.
     */
    public static function getMenuGroups()
    {
        $admin = [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN];
        $office = [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_STAFF];

        $groups = [
            [
                'name' => 'CONTROL',
                'items' => [
                    ['icon' => 'dashboard',   'name' => 'Dashboard',              'path' => '/dashboard',  'roles' => $office, 'permission' => 'view_dashboard'],
                    ['icon' => 'clients',     'name' => 'Clients',                'path' => '/clients',    'roles' => $office, 'permission' => 'view_clients', 'badge' => self::clientBadge()],
                    ['icon' => 'caregivers',  'name' => 'Caregivers',             'path' => '/caregivers', 'roles' => $admin],
                    ['icon' => 'workflow',    'name' => 'Workflow Queues',        'path' => '/workflow-queues', 'roles' => $office, 'badge' => self::workflowQueueBadge()],
                    ['icon' => 'authorize',   'name' => 'Authorizations',         'path' => '/authorizations',  'roles' => $office],
                    ['icon' => 'compliance',  'name' => 'Compliance & Documents', 'path' => '/compliance',      'roles' => $office],
                    ['icon' => 'background',  'name' => 'Background Checks',       'path' => '/background-checks','roles' => $admin],
                ],
            ],
            [
                'name' => 'FINANCIAL',
                'items' => [
                    ['icon' => 'billing', 'name' => 'Billing & Claims', 'path' => '/billing-claims-audit', 'roles' => $office, 'permission' => 'view_billing_claims_audit'],
                    ['icon' => 'payroll', 'name' => 'Payroll',          'path' => '/payroll', 'roles' => $admin, 'permission' => 'view_payroll'],
                ],
            ],
            [
                'name' => 'ENGAGEMENT',
                'items' => [
                    ['icon' => 'comms',     'name' => 'Communications', 'path' => '/communications',  'roles' => $office, 'permission' => 'view_communications', 'active' => ['communications', 'communications/*']],
                    ['icon' => 'calendar',  'name' => 'Schedule',       'path' => '/schedule/board',  'roles' => $office, 'permission' => 'view_calendar', 'active' => ['schedule', 'schedule/*', 'calendar', 'calendar/*']],
                    ['icon' => 'directory', 'name' => 'Directory',      'path' => '/directory', 'roles' => $office, 'active' => ['directory', 'directory/*', 'contacts', 'contacts/*']],
                ],
            ],
            [
                'name' => 'INSIGHTS',
                'items' => [
                    ['icon' => 'reports',           'name' => 'Reports',              'path' => '/reports',           'roles' => $office, 'active' => ['reports', 'reports/*']],
                    ['icon' => 'visit-reports',     'name' => 'Visit Reports',        'path' => '/visit-reports',     'roles' => $office, 'permission' => 'view_visit_reports', 'active' => ['visit-reports', 'visit-reports/*']],
                    ['icon' => 'tasks',             'name' => 'Tasks',                'path' => '/tasks',             'roles' => $office, 'permission' => 'view_tasks', 'active' => ['tasks', 'tasks/*']],
                    ['icon' => 'forms',             'name' => 'Forms',                'path' => '/forms',             'roles' => $office, 'permission' => 'view_forms', 'active' => ['forms', 'forms/*']],
                    ['icon' => 'data-exploration',  'name' => 'Data Exploration',     'path' => '/data-exploration',  'roles' => $office, 'permission' => 'view_data_exploration', 'active' => ['data-exploration', 'data-exploration/*', 'exploration', 'exploration/*']],
                ],
            ],
            [
                'name' => 'MANAGEMENT',
                'items' => [
                    ['icon' => 'ai-agents', 'name' => 'Staff & AI Agents', 'path' => '/staff',           'roles' => $admin, 'permission' => 'view_staff', 'active' => ['staff', 'staff/*']],
                    ['icon' => 'settings',  'name' => 'Settings',          'path' => '/settings',        'roles' => $admin, 'active' => ['settings', 'settings/*']],
                ],
            ],
        ];

        // Filter each group's items by role/permission and drop empty groups.
        return array_values(array_filter(array_map(function ($group) {
            $group['items'] = self::filterItems($group['items']);
            return $group;
        }, $groups), fn ($g) => !empty($g['items'])));
    }

    private static function clientBadge(): ?int
    {
        try {
            $user = auth()->user();
            if (! $user) {
                return null;
            }

            $orgId = $user->isSuperAdmin() ? null : $user->organization_id;

            $count = Cache::remember(
                self::badgeCacheKey('clients', $orgId),
                self::BADGE_CACHE_SECONDS,
                fn () => app(RegistryMetricsService::class)->activeClientCount(),
            );

            return $count > 0 ? $count : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function workflowQueueBadge(): ?int
    {
        try {
            $user = auth()->user();
            if (! $user) {
                return null;
            }

            $orgId = $user->isSuperAdmin() ? null : $user->organization_id;

            $count = Cache::remember(
                self::badgeCacheKey('workflow', $orgId),
                self::BADGE_CACHE_SECONDS,
                fn () => app(WorkflowQueueService::class)->approvalCount($orgId),
            );

            return $count > 0 ? $count : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Clean, single-style line icons (Lucide-flavoured) keyed by icon name.
     * Sized via class so the sidebar controls dimensions consistently.
     */
    public static function getIconSvg($iconName)
    {
        $open = '<svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">';
        $close = '</svg>';

        $paths = [
            'dashboard'  => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
            'clients'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'caregivers' => '<rect x="6" y="2" width="12" height="20" rx="2"/><circle cx="12" cy="10" r="2.5"/><path d="M8.5 17.5a3.5 3.5 0 0 1 7 0"/>',
            'workflow'   => '<path d="M11 12H3"/><path d="M16 6H3"/><path d="M16 18H3"/><path d="m15 10 2 2 4-4"/>',
            'authorize'  => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
            'compliance' => '<rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/>',
            'background' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="m8.5 11 1.7 1.7 3.3-3.4"/>',
            'billing'    => '<rect width="20" height="14" x="2" y="5" rx="2.5"/><line x1="2" x2="22" y1="10" y2="10"/>',
            'payroll'    => '<path d="M19 7V5a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h14a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H5a2 2 0 0 1-2-2V6"/><path d="M16 12.5h.01"/>',
            'comms'      => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
            'calendar'   => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
            'directory'  => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5z"/><path d="M8 7h6M8 11h8"/>',
            'reports'    => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
            'visit-reports' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 11v6"/><path d="M19 14h6"/>',
            'tasks'      => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
            'forms'      => '<rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
            'data-exploration' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M11 8v6"/><path d="M8 11h6"/>',
            'ai-agents'  => '<path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2M20 14h2M15 13v2M9 13v2"/>',
            'settings'   => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        ];

        if (!isset($paths[$iconName])) {
            return $open . '<path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>' . $close;
        }

        return $open . $paths[$iconName] . $close;
    }
}
