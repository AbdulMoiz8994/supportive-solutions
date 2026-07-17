<?php

namespace App\Services\Communication;

use App\Models\Communication;
use App\Models\CommunicationNotification;
use App\Models\SecureMessageThread;
use App\Models\User;
use App\Support\CommunicationOrganizationResolver;

class CommunicationNotificationService
{
    public function notifyCommunicationEvent(User $user, Communication $communication): void
    {
        $type = $communication->status === Communication::STATUS_FAILED
            ? CommunicationNotification::TYPE_COMMUNICATION_FAILED
            : CommunicationNotification::TYPE_COMMUNICATION_SENT;

        $title = $communication->status === Communication::STATUS_FAILED
            ? 'Communication delivery failed'
            : 'Communication sent';

        $body = match ($communication->channel) {
            Communication::CHANNEL_EMAIL => 'An email was processed for your review.',
            Communication::CHANNEL_FAX => 'A fax was processed for your review.',
            Communication::CHANNEL_SMS => 'An SMS was processed for your review.',
            default => 'A communication was logged.',
        };

        $this->create($user, $type, $title, $body, $communication);
    }

    public function notifySecureMessage(User $recipient, SecureMessageThread $thread): void
    {
        $this->create(
            $recipient,
            CommunicationNotification::TYPE_SECURE_MESSAGE,
            'New secure message',
            'You have a new secure message in your inbox.',
            $thread
        );
    }

    public function create(
        User $user,
        string $type,
        string $title,
        string $body,
        ?object $related = null
    ): CommunicationNotification {
        return CommunicationNotification::create([
            'organization_id' => $this->resolveOrganizationId($user, $related),
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'related_type' => $related ? $related::class : null,
            'related_id' => $related?->id,
        ]);
    }

    public function unreadCount(User $user): int
    {
        return CommunicationNotification::query()
            ->where('user_id', $user->id)
            ->when($user->organization_id, fn ($q) => $q->where('organization_id', $user->organization_id))
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(CommunicationNotification $notification, User $user): void
    {
        if ((int) $notification->user_id !== (int) $user->id) {
            abort(403);
        }

        $notification->update(['read_at' => now()]);
    }

    public function markAllAsRead(User $user): int
    {
        return CommunicationNotification::query()
            ->where('user_id', $user->id)
            ->when($user->organization_id, fn ($q) => $q->where('organization_id', $user->organization_id))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    protected function resolveOrganizationId(User $user, ?object $related = null): int
    {
        if ($related && ! empty($related->organization_id)) {
            return (int) $related->organization_id;
        }

        return CommunicationOrganizationResolver::resolve($user);
    }
}
