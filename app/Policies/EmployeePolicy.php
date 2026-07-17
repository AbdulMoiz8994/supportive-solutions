<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use App\Policies\Concerns\InteractsWithOrganization;

class EmployeePolicy
{
    use InteractsWithOrganization;

    public function viewAny(User $user): bool
    {
        return $this->isOfficeTeam($user);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->isOfficeTeam($user) && $this->sameOrganization($user, $employee);
    }

    public function create(User $user): bool
    {
        return $this->isOfficeTeam($user);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->isOfficeTeam($user) && $this->sameOrganization($user, $employee);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->update($user, $employee);
    }

    public function viewCaregiver(User $user, Employee $employee): bool
    {
        return $employee->position === 'Caregiver' && $this->view($user, $employee);
    }

    public function createCaregiver(User $user): bool
    {
        return $this->create($user);
    }

    public function updateCaregiver(User $user, Employee $employee): bool
    {
        return $employee->position === 'Caregiver' && $this->update($user, $employee);
    }
}
