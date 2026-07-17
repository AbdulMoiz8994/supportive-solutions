<?php

namespace App\Policies;

use App\Models\SecureMessageThread;
use App\Models\User;
use App\Policies\Concerns\InteractsWithOrganization;

class SecureMessageThreadPolicy
{
    use InteractsWithOrganization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'manage_secure_messages')
            || $this->hasPermission($user, 'view_communications');
    }

    public function view(User $user, SecureMessageThread $thread): bool
    {
        if (! $this->sameOrganization($user, $thread)) {
            return false;
        }

        if ($user->hasPermission('manage_secure_messages')) {
            return true;
        }

        return $thread->participants()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'manage_secure_messages')
            || $this->hasPermission($user, 'send_communications');
    }

    public function reply(User $user, SecureMessageThread $thread): bool
    {
        return $this->view($user, $thread);
    }
}
