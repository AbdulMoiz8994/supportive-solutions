<?php

namespace App\Services\Communication;

use App\Models\BillingClaimAudit;
use App\Models\CaregiverCommunication;
use App\Models\Client;
use App\Models\Communication;
use App\Models\Employee;
use App\Support\CommunicationOrganizationResolver;
use App\Services\Integrations\GoogleWorkspaceClient;
use App\Services\Integrations\RingCentralClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CommunicationInboundService
{
    public function __construct(
        protected RingCentralClient $ringCentral,
        protected GoogleWorkspaceClient $google,
        protected CommunicationAiSummaryService $aiSummary,
        protected CommunicationWorkflowQueueService $workflowQueue,
    ) {}

    /**
     * @return list<Communication>
     */
    public function recordFromRingCentralWebhook(array $payload): array
    {
        $records = [];

        if ($embedded = $payload['message'] ?? null) {
            if (is_array($embedded)) {
                $record = $this->recordRingCentralMessage($embedded);
                if ($record) {
                    $records[] = $record;
                }
            }

            return $records;
        }

        if ($call = $payload['call'] ?? null) {
            if (is_array($call)) {
                $record = $this->recordRingCentralCall($call);
                if ($record) {
                    $records[] = $record;
                }
            }

            return $records;
        }

        $changes = data_get($payload, 'body.changes', []);

        if (! is_array($changes)) {
            return $records;
        }

        foreach ($changes as $change) {
            if (! is_array($change)) {
                continue;
            }

            $type = strtoupper((string) ($change['type'] ?? ''));

            if (in_array($type, ['SMS', 'FAX', 'VOICEMAIL', 'VOICE'], true)) {
                foreach ($change['newMessageIds'] ?? [] as $messageId) {
                    $message = $this->ringCentral->getMessageStoreEntry((string) $messageId);

                    if ($message) {
                        $record = $this->recordRingCentralMessage($message);
                        if ($record) {
                            $records[] = $record;
                        }
                    }
                }
            }
        }

        return $records;
    }

    /**
     * @return list<Communication>
     */
    public function syncGoogleInbound(int $limit = 25): array
    {
        $records = [];

        foreach ($this->google->listInboundMessages($limit) as $message) {
            $record = $this->recordGoogleMessage($message);
            if ($record) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * Poll the RingCentral message store (SMS / fax / voicemail) — same records
     * a webhook would deliver, so environments without webhooks still ingest
     * inbound texts. Idempotent via provider_message_id.
     *
     * @return list<Communication>
     */
    public function syncRingCentralMessages(int $limit = 25): array
    {
        $records = [];

        foreach ($this->ringCentral->listRecentMessages($limit) as $message) {
            $record = $this->recordRingCentralMessage($message);
            if ($record) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @return list<Communication>
     */
    public function syncRingCentralCalls(int $limit = 25): array
    {
        $records = [];

        foreach ($this->ringCentral->listRecentCallLog($limit) as $call) {
            $record = $this->recordRingCentralCall($call);
            if ($record) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordFromRetellWebhook(array $payload): ?Communication
    {
        $event = (string) ($payload['event'] ?? '');
        $call = $payload['call'] ?? $payload;

        if (! is_array($call)) {
            return null;
        }

        if (! in_array($event, ['call_ended', 'call_analyzed', ''], true)) {
            return null;
        }

        $callId = (string) ($call['call_id'] ?? $call['id'] ?? '');

        if ($callId !== '' && Communication::query()->where('provider_message_id', $callId)->exists()) {
            return null;
        }

        $transcript = $call['transcript'] ?? $call['transcript_object'] ?? [];
        $transcriptText = is_string($transcript)
            ? $transcript
            : collect(is_array($transcript) ? $transcript : [])
                ->map(fn ($line) => is_array($line)
                    ? trim(($line['role'] ?? 'speaker').': '.($line['content'] ?? $line['text'] ?? ''))
                    : (string) $line)
                ->filter()
                ->implode("\n");

        $dynamic = $call['retell_llm_dynamic_variables'] ?? $call['metadata'] ?? [];
        $employeeId = is_array($dynamic) ? ($dynamic['employee_id'] ?? null) : null;
        $clientId = is_array($dynamic) ? ($dynamic['client_id'] ?? null) : null;
        $wellness = is_array($dynamic) && (($dynamic['wellness_call'] ?? false) === true || ($dynamic['campaign'] ?? '') === 'wellness');

        $employee = $employeeId ? Employee::withoutGlobalScopes()->find($employeeId) : null;
        $client = $clientId ? Client::withoutGlobalScopes()->find($clientId) : null;
        $organizationId = $employee?->organization_id ?? $client?->organization_id ?? $this->defaultOrganizationId();

        if (! $organizationId) {
            return null;
        }

        $partyName = $employee
            ? trim($employee->first_name.' '.$employee->last_name)
            : ($client ? trim($client->first_name.' '.$client->last_name) : 'Caller');

        $summary = $this->aiSummary->summarize(
            $transcriptText ?: (string) ($call['call_summary'] ?? 'Voice call completed.'),
            $wellness ? 'Monthly wellness call' : 'Voice call',
            Communication::CHANNEL_CALL,
            Communication::DIRECTION_INBOUND,
            $partyName,
        );

        $concern = $this->detectConcern($transcriptText);
        $handledBy = $concern ? 'concern' : ($wellness ? 'ai_va' : 'needs_review');

        // Wellness call completed without a concern: the delivered services are
        // confirmed, so flip this month's submitted compliance form(s) to
        // Verified (client review D4 — verification workflow).
        if ($wellness && ! $concern) {
            $callPeriod = is_array($dynamic) && preg_match('/^\d{4}-\d{2}$/', (string) ($dynamic['period'] ?? ''))
                ? (string) $dynamic['period']
                : null;

            $this->verifyComplianceForms($client, $employee, $summary, $callPeriod);
        }

        $record = Communication::create([
            'organization_id' => $organizationId,
            'related_type' => $client ? Client::class : ($employee ? Employee::class : null),
            'related_id' => $client?->id ?? $employee?->id,
            'channel' => Communication::CHANNEL_CALL,
            'direction' => Communication::DIRECTION_INBOUND,
            'subject' => $wellness ? 'Monthly wellness call' : 'Inbound call',
            'body' => $transcriptText,
            'status' => Communication::STATUS_RECEIVED,
            'provider_message_id' => $callId !== '' ? $callId : null,
            'sent_at' => now(),
            'metadata' => [
                'handled_by' => $handledBy,
                'party_name' => $partyName,
                'party_type' => $employee ? 'caregiver' : ($client ? 'client' : 'all'),
                'ai_summary' => $summary,
                'wellness_call' => $wellness,
                'provider' => 'Retell',
                'duration_seconds' => (int) ($call['duration_ms'] ?? 0) / 1000,
                'transcript' => $this->formatTranscript($transcript),
                'concern' => $concern,
                'concern_flagged' => $concern !== null,
                'delivery_status' => 'received',
                'source' => 'retell_webhook',
            ],
        ]);

        if ($handledBy === 'needs_review') {
            $this->workflowQueue->syncInboundItem($record);
        }

        $this->syncToCaregiverProfile($record, [
            'related_type' => $employee ? Employee::class : ($client ? Client::class : null),
            'related_id'   => $employee?->id ?? $client?->id,
            'wellness'     => $wellness,
        ]);

        return $record;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public function recordRingCentralMessage(array $message): ?Communication
    {
        $direction = strtolower((string) ($message['direction'] ?? 'inbound'));

        if ($direction !== 'inbound') {
            return null;
        }

        $type = strtoupper((string) ($message['type'] ?? $message['messageType'] ?? 'SMS'));
        $channel = match ($type) {
            'FAX' => Communication::CHANNEL_FAX,
            'VOICEMAIL', 'VOICE' => Communication::CHANNEL_CALL,
            default => Communication::CHANNEL_SMS,
        };

        $from = $this->extractPhone($message, 'from');
        $to = $this->extractPhone($message, 'to');
        $body = (string) ($message['subject'] ?? $message['text'] ?? $message['body'] ?? $message['attachmentContent'] ?? '');
        $providerId = (string) ($message['id'] ?? $message['messageId'] ?? '');

        if ($providerId !== '' && Communication::query()->where('provider_message_id', $providerId)->exists()) {
            return null;
        }

        $client = $this->resolveClientByPhone($from);
        $employee = $client ? null : $this->resolveEmployeeByPhone($from);
        $relatedModel = $client ?? $employee;
        $organizationId = $relatedModel?->organization_id ?? $this->defaultOrganizationId();

        if (! $organizationId) {
            return null;
        }

        $partyName = $this->extractPartyName($message) ?? $from ?? 'Unknown caller';

        return $this->createInboundRecord([
            'organization_id' => $organizationId,
            'related_type' => $client ? Client::class : ($employee ? Employee::class : null),
            'related_id' => $relatedModel?->id,
            'channel' => $channel,
            'subject' => match ($channel) {
                Communication::CHANNEL_FAX => 'Inbound eFax',
                Communication::CHANNEL_CALL => 'Voicemail',
                default => null,
            },
            'body' => $body,
            'recipient_phone' => $to,
            'provider_message_id' => $providerId !== '' ? $providerId : null,
            'party_name' => $partyName,
            'party_type' => $client ? 'client' : ($employee ? 'caregiver' : 'all'),
            'party_context' => $relatedModel ? null : $from,
            'provider' => 'RingCentral',
            'inbound_from' => $from,
            'source' => 'ringcentral_webhook',
        ]);
    }

    /**
     * @param  array<string, mixed>  $call
     */
    public function recordRingCentralCall(array $call): ?Communication
    {
        $direction = strtolower((string) ($call['direction'] ?? 'inbound'));

        if (! in_array($direction, ['inbound', ''], true)) {
            return null;
        }

        $providerId = (string) ($call['id'] ?? $call['sessionId'] ?? '');

        if ($providerId !== '' && Communication::query()->where('provider_message_id', $providerId)->exists()) {
            return null;
        }

        $from = $this->extractPhone($call, 'from') ?? (string) ($call['fromNumber'] ?? $call['from']['phoneNumber'] ?? '');
        $client = $this->resolveClientByPhone($from);
        $employee = $client ? null : $this->resolveEmployeeByPhone($from);
        $relatedModel = $client ?? $employee;
        $organizationId = $relatedModel?->organization_id ?? $this->defaultOrganizationId();

        if (! $organizationId) {
            return null;
        }

        $duration = (int) ($call['duration'] ?? $call['durationSeconds'] ?? 0);
        $result = (string) ($call['result'] ?? $call['action'] ?? 'completed');
        $partyName = $this->extractPartyName($call) ?? $from ?: 'Caller';

        return $this->createInboundRecord([
            'organization_id' => $organizationId,
            'related_type' => $client ? Client::class : ($employee ? Employee::class : null),
            'related_id' => $relatedModel?->id,
            'channel' => Communication::CHANNEL_CALL,
            'subject' => 'Inbound call · '.$result,
            'body' => (string) ($call['subject'] ?? "Inbound call ({$duration}s)"),
            'recipient_phone' => $this->extractPhone($call, 'to'),
            'provider_message_id' => $providerId !== '' ? $providerId : null,
            'party_name' => $partyName,
            'party_type' => $client ? 'client' : ($employee ? 'caregiver' : 'all'),
            'duration_seconds' => $duration,
            'provider' => 'RingCentral',
            'inbound_from' => $from,
            'source' => 'ringcentral_call_log',
        ]);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public function recordGoogleMessage(array $message): ?Communication
    {
        $providerId = (string) ($message['id'] ?? '');

        if ($providerId !== '' && Communication::query()->where('provider_message_id', $providerId)->exists()) {
            return null;
        }

        $from = (string) ($message['from'] ?? '');
        $subject = (string) ($message['subject'] ?? '');
        $body = (string) ($message['body'] ?? '');
        $client = $this->resolveClientByEmail($from);
        $organizationId = $client?->organization_id ?? $this->defaultOrganizationId();

        if (! $organizationId) {
            return null;
        }

        return $this->createInboundRecord([
            'organization_id' => $organizationId,
            'related_type' => $client ? Client::class : null,
            'related_id' => $client?->id,
            'channel' => Communication::CHANNEL_EMAIL,
            'subject' => $subject !== '' ? $subject : 'Inbound email',
            'body' => $body,
            'recipient_email' => (string) config('google_workspace.delegated_user'),
            'provider_message_id' => $providerId !== '' ? $providerId : null,
            'party_name' => $this->extractEmailName($from) ?? $from,
            'party_type' => $client ? 'client' : 'all',
            'provider' => 'Google Workspace',
            'source' => 'google_inbound_sync',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createInboundRecord(array $data): Communication
    {
        $body = (string) ($data['body'] ?? '');
        $subject = (string) ($data['subject'] ?? '');
        $channel = (string) $data['channel'];
        $partyName = (string) ($data['party_name'] ?? 'Unknown');

        $summary = $this->aiSummary->summarize($body, $subject, $channel, Communication::DIRECTION_INBOUND, $partyName);
        $billingClaimId = $this->resolveBillingClaimId($data['related_id'] ?? null, $body, $subject);
        $category = $this->detectCategory($body, $subject);

        $metadata = array_filter([
            'handled_by' => 'needs_review',
            'party_name' => $partyName,
            'party_type' => $data['party_type'] ?? 'all',
            'party_context' => $data['party_context'] ?? null,
            'ai_summary' => $summary,
            'provider' => $data['provider'] ?? null,
            'inbound_from' => $data['inbound_from'] ?? null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'delivery_status' => 'received',
            'source' => $data['source'] ?? 'inbound',
            'billing_claim_audit_id' => $billingClaimId,
            'mco_portal' => $billingClaimId ? true : null,
        ], fn ($value) => $value !== null);

        $concernFlagged = false;

        if ($concern = $this->detectConcern($body)) {
            $metadata['handled_by'] = 'concern';
            $metadata['concern'] = $concern;
            $metadata['concern_flagged'] = true;
            $concernFlagged = true;

            if ($category === Communication::TRIAGE_CATEGORY_GENERAL) {
                $category = Communication::TRIAGE_CATEGORY_CONCERN;
            }
        }

        $record = Communication::create([
            'organization_id' => $data['organization_id'],
            'related_type' => $data['related_type'] ?? null,
            'related_id' => $data['related_id'] ?? null,
            'channel' => $channel,
            'direction' => Communication::DIRECTION_INBOUND,
            'subject' => $subject !== '' ? $subject : null,
            'body' => $body,
            'status' => Communication::STATUS_RECEIVED,
            'recipient_phone' => $data['recipient_phone'] ?? null,
            'recipient_email' => $data['recipient_email'] ?? null,
            'provider_message_id' => $data['provider_message_id'] ?? null,
            'sent_at' => now(),
            'metadata' => $metadata,
            'ai_triage_category' => $category,
            'ai_triage_priority' => $concernFlagged ? Communication::TRIAGE_PRIORITY_URGENT : Communication::TRIAGE_PRIORITY_NORMAL,
            'concern_flagged' => $concernFlagged,
        ]);

        $this->workflowQueue->syncInboundItem($record);
        $this->syncToCaregiverProfile($record, $data);

        return $record;
    }

    /**
     * Apply (or re-apply) AI triage to an already-persisted inbound Communication.
     * Used for manually logged entries and as a stand-alone triage refresh.
     */
    public function applyInboundTriage(Communication $communication): Communication
    {
        if ($communication->direction !== Communication::DIRECTION_INBOUND) {
            return $communication;
        }

        $body = (string) ($communication->body ?? '');
        $subject = (string) ($communication->subject ?? '');
        $channel = (string) $communication->channel;
        $metadata = $communication->metadata ?? [];
        $partyName = (string) ($metadata['party_name'] ?? $communication->recipient_name ?? 'Unknown');

        $summary = $this->aiSummary->summarize($body, $subject, $channel, Communication::DIRECTION_INBOUND, $partyName);
        $category = $this->detectCategory($body, $subject);

        $metadata['ai_summary'] = $summary;
        $metadata['source'] = $metadata['source'] ?? 'manual';

        if (! isset($metadata['handled_by'])) {
            $metadata['handled_by'] = 'needs_review';
        }

        $concernFlagged = false;

        if ($concern = $this->detectConcern($body)) {
            $metadata['handled_by'] = 'concern';
            $metadata['concern'] = $concern;
            $metadata['concern_flagged'] = true;
            $concernFlagged = true;

            if ($category === Communication::TRIAGE_CATEGORY_GENERAL) {
                $category = Communication::TRIAGE_CATEGORY_CONCERN;
            }
        }

        $communication->update([
            'metadata' => $metadata,
            'ai_triage_category' => $category,
            'ai_triage_priority' => $concernFlagged ? Communication::TRIAGE_PRIORITY_URGENT : Communication::TRIAGE_PRIORITY_NORMAL,
            'concern_flagged' => $concernFlagged,
        ]);

        $communication->refresh();

        $this->workflowQueue->syncInboundItem($communication);

        return $communication;
    }

    /**
     * Mirror an inbound Communication that belongs to an Employee onto the
     * caregiver_communications profile feed so it is visible on the caregiver
     * profile without a separate query to the main communications table.
     *
     * @param  array<string, mixed>  $data
     */
    protected function syncToCaregiverProfile(Communication $communication, array $data = []): void
    {
        $relatedType = $data['related_type'] ?? $communication->related_type ?? null;
        $relatedId = $data['related_id'] ?? $communication->related_id ?? null;

        if ($relatedType !== Employee::class || ! $relatedId) {
            return;
        }

        $isWellness = (bool) ($data['wellness'] ?? false);
        $metadata = $communication->metadata ?? [];
        $durationSeconds = (int) ($metadata['duration_seconds'] ?? 0);
        $provider = (string) ($metadata['provider'] ?? '');

        $channel = match ($communication->channel) {
            Communication::CHANNEL_CALL => $isWellness ? 'Wellness' : 'Call',
            Communication::CHANNEL_SMS  => 'SMS',
            Communication::CHANNEL_FAX  => 'Email',
            default                     => 'App',
        };

        $metaParts = array_filter([
            $provider ?: null,
            $durationSeconds > 0 ? gmdate('G\m i\s', $durationSeconds) : null,
        ]);

        CaregiverCommunication::create([
            'organization_id' => $communication->organization_id,
            'employee_id'     => $relatedId,
            'title'           => $communication->subject ?? match ($communication->channel) {
                Communication::CHANNEL_CALL => 'Inbound call',
                Communication::CHANNEL_SMS  => 'Inbound SMS',
                Communication::CHANNEL_FAX  => 'Inbound eFax',
                default                     => 'Inbound message',
            },
            'channel'     => $channel,
            'direction'   => 'Inbound',
            'body'        => $communication->body,
            'tag'         => 'AI Secretary',
            'meta'        => $metaParts ? implode(' · ', $metaParts) : null,
            'occurred_at' => $communication->sent_at ?? $communication->created_at,
        ]);
    }

    protected function verifyComplianceForms(?Client $client, ?Employee $employee, ?string $summary, ?string $period = null): void
    {
        if (! $client && ! $employee) {
            return;
        }

        \App\Models\ComplianceForm::query()
            ->when($client, fn ($q) => $q->where('client_id', $client->id))
            ->when(! $client && $employee, fn ($q) => $q->where('employee_id', $employee->id))
            ->where('period', $period ?? now()->format('Y-m'))
            ->where('status', \App\Models\ComplianceForm::STATUS_SUBMITTED)
            ->get()
            ->each(fn (\App\Models\ComplianceForm $form) => $form->update([
                'status' => \App\Models\ComplianceForm::STATUS_VERIFIED,
                'wellness_call_note' => $summary ?: 'Confirmed by monthly wellness call.',
            ]));
    }

    protected function resolveBillingClaimId(?int $clientId, string $body, string $subject): ?int
    {
        $haystack = strtolower($body.' '.$subject);

        if (! str_contains($haystack, 'billing') && ! str_contains($haystack, 'claim') && ! str_contains($haystack, 'authorization') && ! str_contains($haystack, 'mco')) {
            return null;
        }

        if (! $clientId) {
            return null;
        }

        return BillingClaimAudit::withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->latest('billing_period')
            ->value('id');
    }

    /**
     * Detect safety or compliance concerns in inbound message text.
     *
     * Uses OpenAI when the AI driver is configured (nuanced detection) and
     * falls back to a keyword scan so no network call ever blocks processing.
     *
     * @return array<string, mixed>|null
     */
    protected function detectConcern(string $text): ?array
    {
        if (trim($text) === '') {
            return null;
        }

        $lower = strtolower($text);
        $keywords = [
            'concern', 'emergency', 'hospital', 'fall', 'injury',
            'abuse', 'neglect', 'unsafe', 'crisis', 'danger', 'hurt',
            'pain', 'medication error', 'refused care',
        ];

        $keywordHit = false;

        foreach ($keywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                $keywordHit = true;
                break;
            }
        }

        if (config('communications.ai.driver') === 'openai' && filled(config('communications.ai.openai_api_key'))) {
            return $this->detectConcernWithAi($text, $keywordHit);
        }

        if ($keywordHit) {
            return [
                'label'          => 'Concern flagged',
                'billing_impact' => str_contains($lower, 'billing') || str_contains($lower, 'claim') ? 'Review' : 'None',
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function detectConcernWithAi(string $text, bool $keywordHint): ?array
    {
        if (strlen($text) < 10) {
            return $keywordHint ? ['label' => 'Concern flagged', 'billing_impact' => 'None'] : null;
        }

        try {
            $response = Http::withToken(config('communications.ai.openai_api_key'))
                ->acceptJson()
                ->timeout((int) config('communications.ai.timeout', 20))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('communications.ai.openai_model', 'gpt-4o-mini'),
                    'messages' => [
                        [
                            'role'    => 'system',
                            'content' => 'You are a home-care safety monitor. Respond ONLY with JSON: {"concern":true,"label":"brief label","billing_impact":"None|Review"} or {"concern":false}. Flag true ONLY for genuine safety emergencies, falls, abuse, neglect, medical crises, or unsafe situations. Routine scheduling, billing questions, and general inquiries must NOT be flagged.',
                        ],
                        [
                            'role'    => 'user',
                            'content' => "Evaluate for safety concern:\n{$text}",
                        ],
                    ],
                    'max_tokens'      => 60,
                    'temperature'     => 0.1,
                    'response_format' => ['type' => 'json_object'],
                ]);
        } catch (\Throwable) {
            return $keywordHint ? ['label' => 'Concern flagged', 'billing_impact' => 'None'] : null;
        }

        if (! $response->successful()) {
            return $keywordHint ? ['label' => 'Concern flagged', 'billing_impact' => 'None'] : null;
        }

        $result = $response->json();

        if (is_array($result) && ($result['concern'] ?? false) === true) {
            return [
                'label'          => (string) ($result['label'] ?? 'Concern flagged'),
                'billing_impact' => (string) ($result['billing_impact'] ?? 'None'),
            ];
        }

        return null;
    }

    /**
     * Classify the primary intent of an inbound message for structured triage.
     */
    protected function detectCategory(string $body, string $subject): string
    {
        $text = strtolower($body.' '.$subject);

        return match (true) {
            str_contains($text, 'billing') || str_contains($text, 'claim') || str_contains($text, 'invoice') || str_contains($text, 'mco') || str_contains($text, 'authorization') || str_contains($text, 'auth') => Communication::TRIAGE_CATEGORY_BILLING,
            str_contains($text, 'appointment') || str_contains($text, 'schedule') || str_contains($text, 'reschedule') || str_contains($text, 'cancel') || str_contains($text, 'available') => Communication::TRIAGE_CATEGORY_SCHEDULING,
            str_contains($text, 'wellness') || str_contains($text, 'check-in') || str_contains($text, 'monthly call') => Communication::TRIAGE_CATEGORY_WELLNESS,
            str_contains($text, 'medication') || str_contains($text, 'pharmacy') || str_contains($text, 'rx') || str_contains($text, 'prescription') || str_contains($text, 'doctor') || str_contains($text, 'nurse') => Communication::TRIAGE_CATEGORY_CLINICAL,
            str_contains($text, 'concern') || str_contains($text, 'emergency') || str_contains($text, 'fall') || str_contains($text, 'abuse') || str_contains($text, 'neglect') || str_contains($text, 'unsafe') => Communication::TRIAGE_CATEGORY_CONCERN,
            default => Communication::TRIAGE_CATEGORY_GENERAL,
        };
    }

    /**
     * @param  array|string  $transcript
     * @return list<array{speaker: string, text: string}>
     */
    protected function formatTranscript(array|string $transcript): array
    {
        if (is_string($transcript)) {
            return [['speaker' => 'Transcript', 'text' => $transcript]];
        }

        return collect($transcript)
            ->map(fn ($line) => is_array($line)
                ? ['speaker' => (string) ($line['role'] ?? 'Speaker'), 'text' => (string) ($line['content'] ?? $line['text'] ?? '')]
                : ['speaker' => 'Speaker', 'text' => (string) $line])
            ->filter(fn ($line) => $line['text'] !== '')
            ->values()
            ->all();
    }

    protected function resolveEmployeeByPhone(?string $phone): ?Employee
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        $lastTen = strlen($digits) > 10 ? substr($digits, -10) : $digits;

        return Employee::withoutGlobalScopes()
            ->whereNotNull('phone')
            ->get()
            ->first(function (Employee $employee) use ($lastTen) {
                $empDigits = preg_replace('/\D/', '', (string) $employee->phone) ?? '';

                return $empDigits !== '' && str_ends_with($empDigits, $lastTen);
            });
    }

    protected function resolveClientByPhone(?string $phone): ?Client
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        $lastTen = strlen($digits) > 10 ? substr($digits, -10) : $digits;

        return Client::withoutGlobalScopes()
            ->whereNotNull('phone')
            ->get()
            ->first(function (Client $client) use ($lastTen) {
                $clientDigits = preg_replace('/\D/', '', (string) $client->phone) ?? '';

                return $clientDigits !== '' && str_ends_with($clientDigits, $lastTen);
            });
    }

    protected function resolveClientByEmail(string $email): ?Client
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return null;
        }

        if (preg_match('/<([^>]+)>/', $email, $matches)) {
            $email = strtolower($matches[1]);
        }

        return Client::withoutGlobalScopes()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    protected function extractEmailName(string $from): ?string
    {
        if (preg_match('/^(.+?)\s*<[^>]+>$/', trim($from), $matches)) {
            return trim($matches[1], '" ');
        }

        return null;
    }

    protected function defaultOrganizationId(): ?int
    {
        try {
            return CommunicationOrganizationResolver::defaultOrganizationId();
        } catch (\Illuminate\Validation\ValidationException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function extractPhone(array $message, string $side): ?string
    {
        $value = $message[$side] ?? null;

        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            if (isset($value['phoneNumber'])) {
                return (string) $value['phoneNumber'];
            }

            $first = $value[0] ?? null;

            if (is_array($first) && isset($first['phoneNumber'])) {
                return (string) $first['phoneNumber'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function extractPartyName(array $message): ?string
    {
        foreach (['from', 'to'] as $side) {
            $value = $message[$side] ?? null;

            if (is_array($value) && isset($value['name']) && filled($value['name'])) {
                return (string) $value['name'];
            }
        }

        return null;
    }
}
