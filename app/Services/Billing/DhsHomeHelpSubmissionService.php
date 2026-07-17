<?php

namespace App\Services\Billing;

use App\Models\BillingClaimAudit;
use App\Models\Client;
use App\Models\Communication;
use App\Models\Contact;
use App\Models\User;
use App\Services\Communication\CommunicationSendService;
use App\Services\Communication\CommunicationIntegrationStatusService;
use Illuminate\Support\Facades\Log;

class DhsHomeHelpSubmissionService
{
    public function __construct(
        protected CommunicationSendService $communicationSendService,
        protected CommunicationIntegrationStatusService $integrationStatus,
        protected SigmaPortalBillingService $sigmaPortalBillingService,
    ) {}

    /**
     * @return array{success: bool, channel: string, message: string, communication_id: ?int}
     */
    public function submit(BillingClaimAudit $audit, User $user): array
    {
        if (! $audit->isDhs()) {
            return [
                'success' => false,
                'channel' => 'sigma_dhs',
                'message' => 'This record is not a DHS Home Help invoice.',
                'communication_id' => null,
            ];
        }

        $audit->loadMissing(['client.coverageType', 'client.contacts', 'employee']);

        $recipient = $this->resolveAswRecipient($audit);

        if (! $recipient['email']) {
            return [
                'success' => false,
                'channel' => 'sigma_dhs',
                'message' => 'No ASW email is on file. Link an ASW contact to the client or set the default ASW email in Global Settings → Billing & Claims.',
                'communication_id' => null,
            ];
        }

        if (! $this->integrationStatus->googleReady()) {
            return [
                'success' => false,
                'channel' => 'sigma_dhs',
                'message' => 'Google Workspace email is not configured. Add credentials in Global Settings → Credential Vault.',
                'communication_id' => null,
            ];
        }

        $contact = $recipient['contact'] ?? $this->ensureAswContact($audit, $recipient);

        $client = $audit->client;
        $subject = sprintf(
            'Home Help Invoice — %s %s, %s',
            $client?->first_name,
            $client?->last_name,
            $audit->billing_period?->format('M Y')
        );

        $body = $this->buildInvoiceEmailBody($audit);

        try {
            $communication = $this->communicationSendService->sendDirectMessage($user, [
                'channel' => Communication::CHANNEL_EMAIL,
                'language' => 'en',
                'subject' => $subject,
                'body' => $body,
                'recipient_type' => 'contact',
                'recipient_id' => $contact->id,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('DHS Home Help ASW email failed', [
                'audit_id' => $audit->id,
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'channel' => 'sigma_dhs',
                'message' => 'Failed to email ASW: '.$exception->getMessage(),
                'communication_id' => null,
            ];
        }

        if ($communication->status !== Communication::STATUS_SENT) {
            return [
                'success' => false,
                'channel' => 'sigma_dhs',
                'message' => 'ASW email delivery failed: '.($communication->failure_reason ?? 'unknown error'),
                'communication_id' => $communication->id,
            ];
        }

        $this->recordEmailLifecycle($audit, $recipient['name'], $user->id);
        $sigmaResult = $this->sigmaPortalBillingService->queuePortalPosting($audit, $user->id);

        $audit->billing_status = BillingClaimAudit::BILLING_SENT;
        $audit->billing_method = 'email_asw';
        $audit->billing_route = 'sigma_portal';
        $audit->status_detail = 'Invoice emailed to ASW · awaiting Sigma posting';
        $audit->syncClaimStatusFromBillingStatus();

        return [
            'success' => true,
            'channel' => 'sigma_dhs',
            'message' => 'Home Help invoice emailed to ASW. '.$sigmaResult['message'],
            'communication_id' => $communication->id,
        ];
    }

    /**
     * @return array{email: ?string, name: ?string, contact: ?Contact}
     */
    protected function resolveAswRecipient(BillingClaimAudit $audit): array
    {
        $client = $audit->client;

        if ($client) {
            $contact = $this->resolveAswContact($client);

            if ($contact?->email) {
                return [
                    'email' => $contact->email,
                    'name' => $contact->name,
                    'contact' => $contact,
                ];
            }
        }

        $fallback = config('billing_claims_audit.default_asw_email');

        if ($fallback) {
            return [
                'email' => $fallback,
                'name' => $audit->authorizing_worker_name ?? 'MDHHS ASW',
                'contact' => null,
            ];
        }

        return ['email' => null, 'name' => null, 'contact' => null];
    }

    protected function ensureAswContact(BillingClaimAudit $audit, array $recipient): Contact
    {
        return Contact::withoutGlobalScopes()->firstOrCreate(
            [
                'organization_id' => $audit->organization_id,
                'email' => $recipient['email'],
            ],
            [
                'name' => $recipient['name'] ?? 'MDHHS ASW',
                'type' => Contact::TYPE_OTHER,
                'is_active' => true,
            ]
        );
    }

    protected function resolveAswContact(Client $client): ?Contact
    {
        $client->loadMissing('contacts');

        return $client->aswContact() ?? $client->caseCoordinator();
    }

    protected function buildInvoiceEmailBody(BillingClaimAudit $audit): string
    {
        $client = $audit->client;
        $periodLabel = $audit->billing_period?->format('F Y') ?? 'billing period';

        return implode("\n", array_filter([
            'Please find the Home Help invoice for the following client:',
            '',
            'Client: '.trim(($client?->first_name ?? '').' '.($client?->last_name ?? '')),
            'Medicaid ID: '.($audit->medicaid_id ?? $client?->member_id ?? '—'),
            'Invoice #: '.$audit->claim_number,
            'Service period: '.$periodLabel,
            'Total hours: '.number_format((float) $audit->total_hours, 2),
            'Rate: $'.number_format((float) $audit->hourly_rate, 2).'/hr',
            'Amount: $'.number_format((float) $audit->total_amount, 2),
            $audit->employee ? 'Caregiver: '.$audit->employee->first_name.' '.$audit->employee->last_name : null,
            '',
            'Payment confirmation is expected via Sigma Portal posting.',
            '',
            'Sent automatically from BeydounTech Billing & Claims Audit.',
        ]));
    }

    protected function recordEmailLifecycle(BillingClaimAudit $audit, ?string $recipientName, ?int $userId): void
    {
        $events = $audit->lifecycle_events ?? [];
        $events[] = [
            'status' => 'completed',
            'title' => 'Home Help invoice emailed to ASW',
            'date' => now()->format('M j, Y'),
            'detail' => $recipientName ? 'to '.$recipientName : 'via Google Workspace',
        ];
        $audit->lifecycle_events = $events;
        $audit->last_action = 'Invoice emailed to ASW';
        $audit->updated_by = $userId;
    }
}
