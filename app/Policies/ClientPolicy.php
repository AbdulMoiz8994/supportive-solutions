<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use App\Policies\Concerns\InteractsWithOrganization;

class ClientPolicy
{
    use InteractsWithOrganization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'view_clients');
    }

    public function view(User $user, Client $client): bool
    {
        return $this->hasPermission($user, 'view_clients') && $this->sameOrganization($user, $client);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'add_clients');
    }

    public function update(User $user, Client $client): bool
    {
        return $this->hasPermission($user, 'edit_clients') && $this->sameOrganization($user, $client);
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->hasPermission($user, 'delete_clients') && $this->sameOrganization($user, $client);
    }

    public function addCareDetail(User $user, Client $client): bool
    {
        return $this->update($user, $client);
    }

    public function sendRequest(User $user, Client $client): bool
    {
        return $this->hasPermission($user, 'send_client_requests')
            && $this->sameOrganization($user, $client);
    }
}
