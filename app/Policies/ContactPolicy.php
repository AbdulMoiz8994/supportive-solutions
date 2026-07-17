<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;
use App\Policies\Concerns\InteractsWithOrganization;

class ContactPolicy
{
    use InteractsWithOrganization;

    public function viewAny(User $user): bool
    {
        return $this->isOfficeTeam($user);
    }

    public function view(User $user, Contact $contact): bool
    {
        return $this->isOfficeTeam($user) && $this->sameOrganization($user, $contact);
    }

    public function create(User $user): bool
    {
        return $this->isOfficeTeam($user);
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->isOfficeTeam($user) && $this->sameOrganization($user, $contact);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->update($user, $contact);
    }
}
