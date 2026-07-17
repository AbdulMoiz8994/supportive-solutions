<?php

namespace App\Policies;

use App\Models\CommunicationNotification;
use App\Models\User;
use App\Policies\Concerns\InteractsWithOrganization;

class CommunicationNotificationPolicy
{
    use InteractsWithOrganization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'view_notifications');
    }

    public function view(User $user, CommunicationNotification $notification): bool
    {
        return $this->viewAny($user)
            && $this->sameOrganization($user, $notification)
            && (int) $notification->user_id === (int) $user->id;
    }

    public function update(User $user, CommunicationNotification $notification): bool
    {
        return $this->view($user, $notification);
    }

    public function manage(User $user): bool
    {
        return $this->hasPermission($user, 'manage_notifications') || $this->viewAny($user);
    }
}
