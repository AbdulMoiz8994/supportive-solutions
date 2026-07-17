<?php

namespace App\Http\Controllers;

use App\Helpers\MenuHelper;
use App\Services\RegistryMetricsService;
use App\Services\WorkflowQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Live sidebar badge counts (client review A9). Reads the same single
 * sources of truth as the rendered sidebar (MenuHelper) so the badge always
 * matches page headers after an approve/resolve action. Fresh counts are
 * written through the badge cache so subsequent page loads stay in sync.
 */
class SidebarBadgesController extends Controller
{
    public function __invoke(
        WorkflowQueueService $workflowQueues,
        RegistryMetricsService $registryMetrics,
    ): JsonResponse {
        $user = auth()->user();
        $orgId = $user->isSuperAdmin() ? null : $user->organization_id;

        $workflowCount = $workflowQueues->approvalCount($orgId);
        $clientCount = $registryMetrics->activeClientCount();

        Cache::put(MenuHelper::badgeCacheKey('workflow', $orgId), $workflowCount, MenuHelper::BADGE_CACHE_SECONDS);
        Cache::put(MenuHelper::badgeCacheKey('clients', $orgId), $clientCount, MenuHelper::BADGE_CACHE_SECONDS);

        return response()->json([
            '/workflow-queues' => $workflowCount,
            '/clients' => $clientCount,
        ]);
    }
}
