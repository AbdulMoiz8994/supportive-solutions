<?php

namespace App\Policies;

use App\Models\RequestTemplate;
use App\Models\User;
use App\Policies\Concerns\InteractsWithOrganization;

class RequestTemplatePolicy
{
    use InteractsWithOrganization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'manage_request_templates') && $this->isOfficeTeam($user);
    }

    public function view(User $user, RequestTemplate $template): bool
    {
        return $this->viewAny($user) && $this->sameOrganization($user, $template);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, RequestTemplate $template): bool
    {
        return $this->viewAny($user) && $this->sameOrganization($user, $template);
    }

    public function delete(User $user, RequestTemplate $template): bool
    {
        return $this->update($user, $template);
    }
}
