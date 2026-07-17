<?php

namespace App\Policies;

use App\Models\Intake;
use App\Models\User;
use App\Policies\Concerns\InteractsWithOrganization;

class IntakePolicy
{
    use InteractsWithOrganization;

    public function viewAny(User $user): bool
    {
        return $this->isOfficeTeam($user);
    }

    public function view(User $user, Intake $intake): bool
    {
        return $this->isOfficeTeam($user) && $this->sameOrganization($user, $intake);
    }

    public function create(User $user): bool
    {
        return $this->isOfficeTeam($user);
    }

    public function update(User $user, Intake $intake): bool
    {
        return $this->isOfficeTeam($user) && $this->sameOrganization($user, $intake);
    }

    public function delete(User $user, Intake $intake): bool
    {
        return $this->update($user, $intake);
    }

    public function convert(User $user, Intake $intake): bool
    {
        return $this->update($user, $intake);
    }

    public function logCall(User $user, Intake $intake): bool
    {
        return $this->update($user, $intake);
    }

    public function scheduleAssessment(User $user, Intake $intake): bool
    {
        return $this->update($user, $intake);
    }

    public function markIneligible(User $user, Intake $intake): bool
    {
        return $this->update($user, $intake);
    }

    public function uploadDocument(User $user, Intake $intake): bool
    {
        return $this->update($user, $intake);
    }
}
