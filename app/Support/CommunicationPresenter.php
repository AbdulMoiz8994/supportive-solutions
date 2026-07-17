<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Communication;
use App\Models\Employee;
use Carbon\Carbon;

class CommunicationPresenter
{
    public function __construct(protected Communication $communication) {}

    public static function make(Communication $communication): self
    {
        return new self($communication);
    }

    public function communication(): Communication
    {
        return $this->communication;
    }

    public function partyName(): string
    {
        return (string) ($this->meta('party_name')
            ?? $this->communication->recipient_name
            ?? $this->relatedName()
            ?? 'Unknown party');
    }

    public function partyContext(): ?string
    {
        return $this->meta('party_context');
    }

    public function partyInitials(): string
    {
        $stored = $this->meta('party_initials');
        if ($stored) {
            return strtoupper((string) $stored);
        }

        $parts = preg_split('/\s+/', trim($this->partyName())) ?: [];

        return strtoupper(collect($parts)->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
    }

    public function channelLabel(): string
    {
        if ($this->isWellnessCall()) {
            return 'Call';
        }

        return match ($this->communication->channel) {
            Communication::CHANNEL_EMAIL => 'Email',
            Communication::CHANNEL_FAX => 'eFax',
            Communication::CHANNEL_SMS => 'SMS',
            Communication::CHANNEL_CALL => 'Call',
            Communication::CHANNEL_INTERNAL_MESSAGE => 'Message',
            Communication::CHANNEL_NOTE => 'Note',
            default => ucfirst(str_replace('_', ' ', $this->communication->channel)),
        };
    }

    public function channelIcon(): string
    {
        if ($this->meta('channel_icon')) {
            return (string) $this->meta('channel_icon');
        }

        if ($this->isWellnessCall()) {
            return 'call';
        }

        return match ($this->communication->channel) {
            Communication::CHANNEL_EMAIL => 'email',
            Communication::CHANNEL_FAX => 'fax',
            Communication::CHANNEL_SMS => 'sms',
            Communication::CHANNEL_CALL => 'call',
            default => 'note',
        };
    }

    public function directionLabel(): string
    {
        return match ($this->communication->direction) {
            Communication::DIRECTION_INBOUND => 'IN',
            Communication::DIRECTION_OUTBOUND => 'OUT',
            default => 'INT',
        };
    }

    public function summary(): string
    {
        if ($ai = $this->aiSummary()) {
            return $ai;
        }

        if ($this->communication->subject) {
            return $this->communication->subject;
        }

        $body = $this->communication->body;
        if (! $body) {
            return '—';
        }

        return \Illuminate\Support\Str::limit($body, 120);
    }

    public function hasArabicTag(): bool
    {
        $lang = strtolower((string) ($this->meta('language') ?? ''));

        return in_array($lang, ['ar', 'arabic'], true) || (bool) $this->meta('bilingual');
    }

    public function handledBy(): string
    {
        $stored = $this->meta('handled_by');
        if ($stored) {
            return (string) $stored;
        }

        if (in_array($this->communication->status, [Communication::STATUS_FAILED, Communication::STATUS_QUEUED], true)) {
            return 'needs_review';
        }

        if ($this->meta('concern')) {
            return 'concern';
        }

        if (in_array($this->communication->channel, [Communication::CHANNEL_SYSTEM, Communication::CHANNEL_INTERNAL_MESSAGE], true)) {
            return 'ai_va';
        }

        return match ($this->communication->status) {
            Communication::STATUS_SENT, Communication::STATUS_READ, Communication::STATUS_RECEIVED => 'ai_va',
            default => 'needs_review',
        };
    }

    public function handledLabel(): string
    {
        return match ($this->handledBy()) {
            'ai_va' => 'AI / VA',
            'concern' => 'Concern → review',
            'needs_review' => 'Needs '.config('communications.queue_owner_label', 'Ali'),
            'staff' => $this->staffHandledLabel(),
            default => $this->staffHandledLabel(),
        };
    }

    public function staffHandledLabel(): string
    {
        if ($name = $this->meta('handled_by_name')) {
            return (string) $name;
        }

        if ($this->communication->relationLoaded('sender') && $this->communication->sender) {
            return $this->communication->sender->name;
        }

        return 'Staff';
    }

    public function profileDirectionLabel(): string
    {
        $channel = $this->channelLabel();

        return match ($this->communication->direction) {
            Communication::DIRECTION_INBOUND => 'Inbound '.$channel,
            Communication::DIRECTION_OUTBOUND => 'Outbound '.$channel,
            default => $channel,
        };
    }

    public function billingClaimUrl(): ?string
    {
        $claimId = $this->meta('billing_claim_audit_id');

        if (! $claimId) {
            return null;
        }

        return route('billing-claims-audit.show', $claimId);
    }

    public function hasBillingLink(): bool
    {
        return $this->billingClaimUrl() !== null || (bool) $this->meta('mco_portal');
    }

    public function billingLinkLabel(): string
    {
        return 'Open in Billing & Claims';
    }

    public function profileMetaLabel(): string
    {
        $parts = array_filter([
            $this->providerLabel(),
            $this->deliveryStatusLabel(),
        ]);

        return $parts !== [] ? implode(' · ', $parts) : $this->channelLabel();
    }

    public function providerLabel(): ?string
    {
        return match ($this->communication->channel) {
            Communication::CHANNEL_SMS, Communication::CHANNEL_FAX, Communication::CHANNEL_CALL => 'RingCentral',
            Communication::CHANNEL_EMAIL => 'Google Workspace',
            default => null,
        };
    }

    public function deliveryStatusLabel(): ?string
    {
        $status = $this->meta('delivery_status');

        if (! $status) {
            return null;
        }

        return match ($status) {
            'delivered' => 'Delivered',
            'failed' => 'Failed',
            'sent' => 'Sent',
            default => ucfirst((string) $status),
        };
    }

    public function handledTone(): string
    {
        return match ($this->handledBy()) {
            'ai_va' => 'green',
            'concern' => 'orange',
            'needs_review' => 'amber',
            'staff' => 'gray',
            default => 'gray',
        };
    }

    public function actionLabel(): string
    {
        return match ($this->handledBy()) {
            'needs_review' => 'Reply',
            'concern' => 'Review',
            default => 'Open',
        };
    }

    public function whenLabel(): string
    {
        $at = $this->communication->sent_at ?? $this->communication->created_at;

        return $at ? Carbon::parse($at)->format('M j, g:i A') : '—';
    }

    public function isWellnessCall(): bool
    {
        if ($this->meta('wellness_call')) {
            return true;
        }

        $subject = strtolower((string) $this->communication->subject);

        return $this->communication->channel === Communication::CHANNEL_CALL
            && str_contains($subject, 'wellness');
    }

    public function partyType(): string
    {
        return (string) ($this->meta('party_type') ?? $this->inferPartyType());
    }

    public function aiSummary(): ?string
    {
        return $this->meta('ai_summary') ?? $this->meta('summary');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function transcript(): array
    {
        $lines = $this->meta('transcript');

        return is_array($lines) ? $lines : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function concern(): ?array
    {
        $concern = $this->meta('concern');

        return is_array($concern) ? $concern : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function acknowledgments(): array
    {
        $items = $this->meta('acknowledgments');

        return is_array($items) ? $items : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function linkedRecords(): array
    {
        $items = $this->meta('linked_records');

        return is_array($items) ? $items : $this->defaultLinkedRecords();
    }

    public function durationLabel(): ?string
    {
        $seconds = $this->meta('duration_seconds');
        if (! $seconds) {
            return null;
        }

        $mins = floor($seconds / 60);
        $secs = $seconds % 60;

        return sprintf('%dm %02ds', $mins, $secs);
    }

    public function contextLine(): string
    {
        $parts = array_filter([
            $this->meta('party_role_label'),
            $this->meta('client_label') ? 'client '.$this->meta('client_label') : null,
            $this->meta('provider') ?? 'Agency channel',
            $this->hasArabicTag() ? 'Arabic' : null,
        ]);

        return implode(' · ', $parts) ?: ucfirst(str_replace('_', ' ', $this->communication->channel));
    }

    public function concernFlagged(): bool
    {
        return $this->handledBy() === 'concern' || (bool) $this->meta('concern_flagged');
    }

    protected function inferPartyType(): string
    {
        if ($this->communication->related_type === Client::class) {
            return 'client';
        }

        if ($this->communication->related_type === Employee::class) {
            return 'caregiver';
        }

        return 'all';
    }

    protected function relatedName(): ?string
    {
        $related = $this->communication->related;
        if ($related instanceof Client || $related instanceof Employee) {
            return trim($related->first_name.' '.$related->last_name);
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function defaultLinkedRecords(): array
    {
        $records = [];
        $related = $this->communication->related;

        if ($claimId = $this->meta('billing_claim_audit_id')) {
            $records[] = [
                'label' => 'Billing & Claims audit #'.$claimId,
                'icon' => 'billing',
                'url' => route('billing-claims-audit.show', $claimId),
            ];
        }

        if ($related instanceof Client) {
            $records[] = [
                'label' => 'Client: '.$related->first_name.' '.$related->last_name,
                'icon' => 'client',
                'url' => route('clients.show', $related->id),
            ];
        }

        if ($related instanceof Employee) {
            $records[] = [
                'label' => 'Caregiver: '.$related->first_name.' '.$related->last_name,
                'icon' => 'caregiver',
                'url' => route('caregivers.show', $related->id),
            ];
        }

        if ($this->communication->attachments->isNotEmpty()) {
            foreach ($this->communication->attachments as $attachment) {
                $records[] = [
                    'label' => 'Attachment: '.$attachment->original_name,
                    'icon' => 'recording',
                    'url' => route('communications.attachments.download', $attachment),
                ];
            }
        }

        return $records;
    }

    protected function meta(string $key): mixed
    {
        return $this->communication->metadata[$key] ?? null;
    }
}
