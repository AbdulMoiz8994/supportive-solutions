<?php

namespace App\Services\Communication;

use App\Models\Client;
use App\Models\Communication;
use App\Models\CommunicationTemplate;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Employee;
use App\Models\User;
use App\Support\CommunicationOrganizationResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommunicationSendService
{
    public function __construct(
        protected CommunicationRecipientResolver $recipientResolver,
        protected CommunicationTemplateRenderService $renderService,
        protected CommunicationChannelManager $channelManager,
        protected CommunicationAttachmentService $attachmentService,
        protected CommunicationNotificationService $notificationService,
        protected CommunicationIntegrationStatusService $integrationStatus,
        protected CommunicationAiSummaryService $aiSummary,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendFromTemplate(
        CommunicationTemplate $template,
        User $sender,
        ?Client $client = null,
        ?Employee $employee = null,
        array $payload = [],
        ?UploadedFile $attachment = null
    ): Communication {
        $this->assertRateLimit($sender);

        if ($template->trashed() || ! $template->is_active) {
            throw ValidationException::withMessages([
                'template_id' => 'The selected template is inactive or unavailable.',
            ]);
        }

        if ($client && (int) $template->organization_id !== (int) $client->organization_id) {
            throw ValidationException::withMessages([
                'template_id' => 'The selected template does not belong to this organization.',
            ]);
        }

        $recipient = $this->recipientResolver->resolve($template, $client, $employee, $payload);
        $this->recipientResolver->assertResolvable($template, $recipient);

        $variables = $this->renderService->buildVariables($client, $employee);

        $allowed = $template->allowed_variables ?: config('communications.template_variables');
        $subject = $this->renderService->render($payload['subject'] ?? $template->subject, $variables, $allowed, false);
        $body = $this->renderService->render($payload['body'] ?? $template->body, $variables, $allowed, false);

        return DB::transaction(function () use ($template, $sender, $client, $employee, $recipient, $subject, $body, $attachment) {
            $communication = Communication::create([
                'organization_id' => $template->organization_id,
                'related_type' => $client ? Client::class : ($employee ? Employee::class : null),
                'related_id' => $client?->id ?? $employee?->id,
                'template_id' => $template->id,
                'channel' => $template->channel,
                'direction' => Communication::DIRECTION_OUTBOUND,
                'subject' => $subject,
                'body' => $body,
                'status' => Communication::STATUS_QUEUED,
                'sender_id' => $sender->id,
                'recipient_type' => $recipient['recipient_type'],
                'recipient_id' => $recipient['recipient_id'],
                'recipient_name' => $recipient['recipient_name'],
                'recipient_email' => $recipient['recipient_email'],
                'recipient_phone' => $recipient['recipient_phone'],
                'recipient_fax' => $recipient['recipient_fax'],
            ]);

            if ($attachment) {
                $this->attachmentService->storeForCommunication($communication, $attachment);
            }

            $result = $this->channelManager->driver($communication->channel)->send($communication);

            $communication->update([
                'status' => $result->success ? Communication::STATUS_SENT : Communication::STATUS_FAILED,
                'provider_message_id' => $result->providerMessageId,
                'failure_reason' => $result->failureReason,
                'sent_at' => $result->success ? now() : null,
            ]);

            $this->notificationService->notifyCommunicationEvent($sender, $communication);

            return $communication->fresh(['attachments', 'template']);
        });
    }

    /**
     * @param  array{
     *     channel: string,
     *     language: string,
     *     body: string,
     *     subject?: ?string,
     *     recipient_type: string,
     *     recipient_id: int,
     *     template_id?: ?int,
     * }  $payload
     */
    public function sendDirectMessage(User $sender, array $payload): Communication
    {
        $this->assertRateLimit($sender);

        $channel = $payload['channel'];
        if ($channel === Communication::CHANNEL_SMS && ! $this->integrationStatus->ringcentralSmsReady()) {
            throw ValidationException::withMessages([
                'channel' => $this->integrationStatus->ringcentralSmsMessage()
                    ?: 'RingCentral SMS is not ready. Configure SMS permission in the RingCentral Developer Portal and update Credential Vault.',
            ]);
        }

        if ($channel === Communication::CHANNEL_EMAIL && ! $this->integrationStatus->googleReady()) {
            throw ValidationException::withMessages([
                'channel' => 'Google Workspace is not connected. Configure it in Global Settings → Credential Vault and test the Directory card.',
            ]);
        }

        $recipient = $this->resolveDirectoryRecipient(
            $sender,
            $payload['recipient_type'],
            (int) $payload['recipient_id']
        );

        $client = $payload['recipient_type'] === 'client'
            ? Client::find($payload['recipient_id'])
            : null;
        $employee = $payload['recipient_type'] === 'employee'
            ? Employee::find($payload['recipient_id'])
            : null;

        $body = $payload['body'];
        $subject = $payload['subject'] ?? null;
        $template = null;

        if (! empty($payload['template_id'])) {
            $template = CommunicationTemplate::findOrFail((int) $payload['template_id']);
            $variables = $this->renderService->buildVariables($client, $employee);
            $allowed = $template->allowed_variables ?: config('communications.template_variables');
            $body = $this->renderService->render($body ?: $template->body, $variables, $allowed, false);
            $subject = $this->renderService->render($subject ?: $template->subject, $variables, $allowed, false);
        }

        if ($channel === Communication::CHANNEL_SMS && empty($recipient['recipient_phone'])) {
            throw ValidationException::withMessages([
                'recipient' => 'No phone number is available for this recipient.',
            ]);
        }

        if ($channel === Communication::CHANNEL_EMAIL && empty($recipient['recipient_email'])) {
            throw ValidationException::withMessages([
                'recipient' => 'No email address is available for this recipient.',
            ]);
        }

        $contact = $payload['recipient_type'] === 'contact'
            ? Contact::find((int) $payload['recipient_id'])
            : null;

        $organizationId = CommunicationOrganizationResolver::resolve($sender, $client, $contact, $employee);

        $summary = $this->aiSummary->summarize(
            (string) $body,
            (string) $subject,
            $channel,
            Communication::DIRECTION_OUTBOUND,
            $recipient['recipient_name'] ?? null,
        );

        return DB::transaction(function () use ($sender, $organizationId, $channel, $recipient, $client, $employee, $body, $subject, $template, $payload, $summary) {
            $communication = Communication::create([
                'organization_id' => $organizationId,
                'related_type' => $client ? Client::class : ($employee ? Employee::class : ($recipient['recipient_type'] === Contact::class ? Contact::class : null)),
                'related_id' => $client?->id ?? $employee?->id ?? ($recipient['recipient_type'] === Contact::class ? $recipient['recipient_id'] : null),
                'template_id' => $template?->id,
                'channel' => $channel,
                'direction' => Communication::DIRECTION_OUTBOUND,
                'subject' => $subject,
                'body' => $body,
                'status' => Communication::STATUS_QUEUED,
                'sender_id' => $sender->id,
                'recipient_type' => $recipient['recipient_type'],
                'recipient_id' => $recipient['recipient_id'],
                'recipient_name' => $recipient['recipient_name'],
                'recipient_email' => $recipient['recipient_email'],
                'recipient_phone' => $recipient['recipient_phone'],
                'metadata' => [
                    'handled_by' => 'staff',
                    'handled_by_name' => $sender->name,
                    'party_name' => $recipient['recipient_name'],
                    'party_context' => $recipient['party_context'],
                    'party_type' => $payload['recipient_type'],
                    'language' => $payload['language'],
                    'ai_summary' => $summary,
                    'composed_via' => 'communications_modal',
                ],
            ]);

            $result = $this->channelManager->driver($communication->channel)->send($communication);

            $communication->update([
                'status' => $result->success ? Communication::STATUS_SENT : Communication::STATUS_FAILED,
                'provider_message_id' => $result->providerMessageId,
                'failure_reason' => $result->failureReason,
                'sent_at' => $result->success ? now() : null,
            ]);

            $this->notificationService->notifyCommunicationEvent($sender, $communication);

            return $communication->fresh(['attachments', 'template']);
        });
    }

    /**
     * @param  array{
     *     recipient_fax?: ?string,
     *     contact_id?: ?int,
     *     client_id?: ?int,
     *     cover_note?: ?string,
     *     document_id?: ?int,
     * }  $payload
     */
    public function sendEfax(User $sender, array $payload, ?UploadedFile $upload = null): Communication
    {
        $this->assertRateLimit($sender);

        if (! $this->integrationStatus->ringcentralFaxReady()) {
            throw ValidationException::withMessages([
                'send' => $this->integrationStatus->ringcentralFaxMessage()
                    ?: 'RingCentral eFax is not ready. Configure Fax permission in the RingCentral Developer Portal and update Credential Vault.',
            ]);
        }

        $client = ! empty($payload['client_id']) ? Client::findOrFail((int) $payload['client_id']) : null;
        $contact = ! empty($payload['contact_id']) ? Contact::find((int) $payload['contact_id']) : null;
        $fax = trim((string) ($payload['recipient_fax'] ?? ''));

        if ($contact && ! $fax) {
            $fax = trim((string) $contact->fax);
        }

        if ($fax === '') {
            throw ValidationException::withMessages([
                'recipient_fax' => 'A fax number is required.',
            ]);
        }

        $coverNote = trim((string) ($payload['cover_note'] ?? ''));
        $subject = $coverNote !== '' ? $coverNote : 'Outbound eFax';
        $organizationId = CommunicationOrganizationResolver::resolve($sender, $client, $contact);

        return DB::transaction(function () use ($sender, $organizationId, $client, $contact, $fax, $coverNote, $subject, $payload, $upload) {
            $communication = Communication::create([
                'organization_id' => $organizationId,
                'related_type' => $client ? Client::class : null,
                'related_id' => $client?->id,
                'channel' => Communication::CHANNEL_FAX,
                'direction' => Communication::DIRECTION_OUTBOUND,
                'subject' => $subject,
                'body' => $coverNote,
                'status' => Communication::STATUS_QUEUED,
                'sender_id' => $sender->id,
                'recipient_type' => $contact ? Contact::class : null,
                'recipient_id' => $contact?->id,
                'recipient_name' => $contact?->name,
                'recipient_fax' => $fax,
                'metadata' => [
                    'handled_by' => 'staff',
                    'handled_by_name' => $sender->name,
                    'party_name' => $contact?->name ?? $fax,
                    'party_context' => $client ? trim($client->first_name.' '.$client->last_name) : ($contact?->clinic_name),
                    'party_type' => $contact ? 'contact' : 'fax',
                    'ai_summary' => $this->aiSummary->summarize($coverNote, $subject, Communication::CHANNEL_FAX, Communication::DIRECTION_OUTBOUND, $contact?->name),
                    'composed_via' => 'communications_modal',
                    'delivery_status' => 'sent',
                ],
            ]);

            if ($upload) {
                $this->attachmentService->storeForCommunication($communication, $upload);
            } elseif (! empty($payload['document_id']) && $client) {
                $this->attachClientDocument($communication, $client, (int) $payload['document_id']);
            } else {
                throw ValidationException::withMessages([
                    'attachment' => 'Attach a PDF or choose a document from the client record.',
                ]);
            }

            $result = $this->channelManager->driver(Communication::CHANNEL_FAX)->send($communication);

            $metadata = $communication->metadata ?? [];
            $metadata['delivery_status'] = $result->success ? 'delivered' : 'failed';

            $communication->update([
                'status' => $result->success ? Communication::STATUS_SENT : Communication::STATUS_FAILED,
                'provider_message_id' => $result->providerMessageId,
                'failure_reason' => $result->failureReason,
                'sent_at' => $result->success ? now() : null,
                'metadata' => $metadata,
            ]);

            $communication->load('attachments');

            if ($client && $communication->attachments->isNotEmpty()) {
                $this->copyAttachmentToClientDocuments($client, $communication->attachments->first(), $sender);
            }

            $this->notificationService->notifyCommunicationEvent($sender, $communication);

            return $communication->fresh(['attachments']);
        });
    }

    /**
     * @return array{
     *     recipient_type: string,
     *     recipient_id: ?int,
     *     recipient_name: string,
     *     recipient_email: ?string,
     *     recipient_phone: ?string,
     *     party_context: string,
     * }
     */
    protected function resolveDirectoryRecipient(User $sender, string $type, int $id): array
    {
        if ($type === 'client') {
            $client = $this->scopedDirectoryQuery(Client::query(), $sender)->findOrFail($id);

            return [
                'recipient_type' => Client::class,
                'recipient_id' => $client->id,
                'recipient_name' => trim($client->first_name.' '.$client->last_name),
                'recipient_email' => $client->email,
                'recipient_phone' => $client->phone,
                'party_context' => 'Client',
            ];
        }

        if ($type === 'employee') {
            $employee = $this->scopedDirectoryQuery(Employee::query(), $sender)->findOrFail($id);

            return [
                'recipient_type' => Employee::class,
                'recipient_id' => $employee->id,
                'recipient_name' => trim($employee->first_name.' '.$employee->last_name),
                'recipient_email' => $employee->email,
                'recipient_phone' => $employee->phone,
                'party_context' => 'Caregiver',
            ];
        }

        $contact = $this->scopedDirectoryQuery(Contact::query(), $sender)->findOrFail($id);

        return [
            'recipient_type' => Contact::class,
            'recipient_id' => $contact->id,
            'recipient_name' => $contact->name,
            'recipient_email' => $contact->email,
            'recipient_phone' => $contact->phone,
            'party_context' => $contact->clinic_name ?: $contact->type,
        ];
    }

    protected function scopedDirectoryQuery($query, User $sender)
    {
        if ($sender->organization_id) {
            $query->where('organization_id', $sender->organization_id);
        }

        return $query;
    }

    protected function attachClientDocument(Communication $communication, Client $client, int $documentId): void
    {
        $document = $client->documents()->whereKey($documentId)->firstOrFail();
        $disk = $document->disk ?: 'local';
        $path = $document->path;

        if (! Storage::disk($disk)->exists($path)) {
            throw ValidationException::withMessages([
                'document_id' => 'The selected client document could not be found.',
            ]);
        }

        $extension = pathinfo($document->original_filename ?: $document->name, PATHINFO_EXTENSION) ?: 'pdf';
        $storedFilename = Str::uuid().'.'.strtolower($extension);
        $targetPath = 'communications/'.$communication->organization_id.'/'.$communication->id.'/'.$storedFilename;

        Storage::disk(CommunicationAttachmentService::DISK)->put(
            $targetPath,
            Storage::disk($disk)->get($path)
        );

        $communication->attachments()->create([
            'organization_id' => $communication->organization_id,
            'original_name' => $document->original_filename ?: $document->name,
            'stored_path' => $targetPath,
            'disk' => CommunicationAttachmentService::DISK,
            'mime_type' => $document->mime_type ?: 'application/pdf',
            'file_size' => (int) ($document->file_size ?? Storage::disk($disk)->size($path)),
        ]);
    }

    protected function copyAttachmentToClientDocuments(Client $client, $attachment, User $sender): void
    {
        if (! Storage::disk($attachment->disk)->exists($attachment->stored_path)) {
            return;
        }

        $targetPath = 'documents/clients/'.$client->id.'/'.basename($attachment->stored_path);
        Storage::disk('local')->put(
            $targetPath,
            Storage::disk($attachment->disk)->get($attachment->stored_path)
        );

        $client->documents()->create([
            'organization_id' => $client->organization_id,
            'name' => $attachment->original_name,
            'path' => $targetPath,
            'disk' => 'local',
            'mime_type' => $attachment->mime_type,
            'file_size' => $attachment->file_size,
            'original_filename' => $attachment->original_name,
            'type' => 'fax',
            'category' => 'Communications',
            'uploaded_by' => $sender->id,
        ]);
    }

    protected function assertRateLimit(User $sender): void
    {
        $key = 'communication-send:'.$sender->id;
        $max = (int) config('communications.send_rate_limit.max_attempts', 10);
        $decay = (int) config('communications.send_rate_limit.decay_seconds', 60);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw ValidationException::withMessages([
                'send' => 'Too many send attempts. Please wait before trying again.',
            ]);
        }

        RateLimiter::hit($key, $decay);
    }
}
