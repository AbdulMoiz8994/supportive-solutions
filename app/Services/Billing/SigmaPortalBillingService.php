<?php

namespace App\Services\Billing;

use App\Models\BillingClaimAudit;
use App\Models\IntegrationCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SigmaPortalBillingService
{
    public function portalUrl(): string
    {
        return (string) (
            config('billing_claims_audit.sigma_portal_url')
            ?? config('global_settings.vault_rpa.'.IntegrationCredential::KEY_SIGMA.'.portal_url')
            ?? 'https://www.michigan.gov/mdhhs'
        );
    }

    /**
     * Queue Sigma portal posting (RPA-ready) after ASW invoice delivery.
     *
     * @return array{success: bool, message: string}
     */
    public function queuePortalPosting(BillingClaimAudit $audit, ?int $userId = null): array
    {
        $portalUrl = $this->portalUrl();
        $reachable = $this->verifyPortalReachable($portalUrl);

        $events = $audit->lifecycle_events ?? [];
        $events[] = [
            'status' => $reachable ? 'current' : 'pending',
            'title' => 'Awaiting Sigma Portal posting',
            'date' => now()->format('M j, Y'),
            'detail' => $reachable
                ? 'Portal reachable · RPA agent can post invoice confirmation'
                : 'Portal URL saved · verify network access before RPA run',
        ];

        $audit->billing_route = 'sigma_portal';
        $audit->lifecycle_events = $events;
        $audit->last_action = 'Sigma portal posting queued';
        $audit->updated_by = $userId;

        Log::channel('single')->info('Sigma portal posting queued for DHS invoice', [
            'audit_id' => $audit->id,
            'portal_url' => $portalUrl,
            'reachable' => $reachable,
        ]);

        return [
            'success' => true,
            'message' => $reachable
                ? 'Sigma Portal posting queued.'
                : 'Sigma Portal posting queued (portal reachability check failed — verify URL in Credential Vault).',
        ];
    }

    protected function verifyPortalReachable(string $url): bool
    {
        try {
            $response = Http::timeout(8)->head($url);

            return $response->successful() || in_array($response->status(), [301, 302, 403], true);
        } catch (\Throwable) {
            return false;
        }
    }
}
