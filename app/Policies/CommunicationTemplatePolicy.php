<?php

namespace App\Policies;

use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Policies\Concerns\InteractsWithOrganization;

class CommunicationTemplatePolicy
{
    use InteractsWithOrganization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'manage_communication_templates') && $this->isOfficeTeam($user);
    }

    public function view(User $user, CommunicationTemplate $template): bool
    {
        return $this->viewAny($user) && $this->sameOrganization($user, $template);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, CommunicationTemplate $template): bool
    {
        return $this->viewAny($user) && $this->sameOrganization($user, $template);
    }

    public function delete(User $user, CommunicationTemplate $template): bool
    {
        return $this->update($user, $template);
    }
}
