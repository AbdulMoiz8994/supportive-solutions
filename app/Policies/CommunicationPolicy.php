<?php

namespace App\Policies;

use App\Models\Communication;
use App\Models\User;
use App\Policies\Concerns\InteractsWithOrganization;

class CommunicationPolicy
{
    use InteractsWithOrganization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'view_communications');
    }

    public function view(User $user, Communication $communication): bool
    {
        return $this->viewAny($user) && $this->sameOrganization($user, $communication);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'send_communications');
    }

    public function send(User $user): bool
    {
        return $this->create($user);
    }

    public function update(User $user, Communication $communication): bool
    {
        return $this->view($user, $communication) && $this->create($user);
    }
}
