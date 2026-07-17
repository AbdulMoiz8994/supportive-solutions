<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CommunicationOrganizationResolver
{
    public static function resolve(
        User $user,
        ?Client $client = null,
        ?Contact $contact = null,
        ?Employee $employee = null,
    ): int {
        if ($user->organization_id) {
            return (int) $user->organization_id;
        }

        if ($client?->organization_id) {
            return (int) $client->organization_id;
        }

        if ($contact?->organization_id) {
            return (int) $contact->organization_id;
        }

        if ($employee?->organization_id) {
            return (int) $employee->organization_id;
        }

        return static::defaultOrganizationId();
    }

    public static function defaultOrganizationId(): int
    {
        $configured = config('communications.inbound.organization_id');
        if ($configured) {
            return (int) $configured;
        }

        $defaultId = Organization::query()->value('id');
        if ($defaultId) {
            return (int) $defaultId;
        }

        throw ValidationException::withMessages([
            'organization_id' => 'No organization context is available.',
        ]);
    }
}
