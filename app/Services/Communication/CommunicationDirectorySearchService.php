<?php

namespace App\Services\Communication;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Employee;
use App\Models\Scopes\LocationScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CommunicationDirectorySearchService
{
    /**
     * @return list<array{type: string, id: int, name: string, context: string, phone: ?string, email: ?string, fax: ?string}>
     */
    public function search(User $user, string $query, int $limit = 12): array
    {
        $query = trim($query);

        if ($query === '' || strlen($query) > 100) {
            return [];
        }

        $perType = max(3, (int) ceil($limit / 3));

        return collect()
            ->merge($this->searchClients($user, $query, $perType))
            ->merge($this->searchEmployees($user, $query, $perType))
            ->merge($this->searchContacts($user, $query, $perType))
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function searchClients(User $user, string $query, int $limit): Collection
    {
        $phoneDigits = $this->phoneDigits($query);

        $builder = Client::query()
            ->withoutGlobalScope(LocationScope::class)
            ->where(function (Builder $q) use ($query, $phoneDigits) {
                $q->where('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('member_id', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");

                if (strlen($phoneDigits) >= 3) {
                    $this->applyPhoneSearch($q, 'phone', $phoneDigits);
                }
            });

        if ($user->organization_id) {
            $builder->where('organization_id', $user->organization_id);
        }

        return $builder
            ->orderBy('last_name')
            ->limit($limit)
            ->get(['id', 'first_name', 'last_name', 'phone', 'email'])
            ->map(fn (Client $client) => [
                'type' => 'client',
                'id' => $client->id,
                'name' => trim($client->first_name.' '.$client->last_name),
                'context' => 'Client',
                'phone' => $client->phone,
                'email' => $client->email,
                'fax' => null,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function searchEmployees(User $user, string $query, int $limit): Collection
    {
        $phoneDigits = $this->phoneDigits($query);

        $builder = Employee::query()
            ->withoutGlobalScope(LocationScope::class)
            ->where(function (Builder $q) use ($query, $phoneDigits) {
                $q->where('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");

                if (strlen($phoneDigits) >= 3) {
                    $this->applyPhoneSearch($q, 'phone', $phoneDigits);
                }
            });

        if ($user->organization_id) {
            $builder->where('organization_id', $user->organization_id);
        }

        return $builder
            ->orderBy('last_name')
            ->limit($limit)
            ->get(['id', 'first_name', 'last_name', 'phone', 'email'])
            ->map(fn (Employee $employee) => [
                'type' => 'employee',
                'id' => $employee->id,
                'name' => trim($employee->first_name.' '.$employee->last_name),
                'context' => 'Caregiver',
                'phone' => $employee->phone,
                'email' => $employee->email,
                'fax' => null,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function searchContacts(User $user, string $query, int $limit): Collection
    {
        $builder = Contact::query()
            ->withoutGlobalScope(LocationScope::class)
            ->whereIn('type', [
                Contact::TYPE_INSURANCE,
                Contact::TYPE_PCP,
                Contact::TYPE_CASE_COORDINATOR,
                Contact::TYPE_VENDOR,
                Contact::TYPE_OTHER,
            ])
            ->directorySearch($query);

        if ($user->organization_id) {
            $builder->where('organization_id', $user->organization_id);
        }

        return $builder
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'type', 'phone', 'email', 'fax', 'clinic_name'])
            ->map(fn (Contact $contact) => [
                'type' => 'contact',
                'id' => $contact->id,
                'name' => $contact->name,
                'context' => $this->contactContext($contact),
                'phone' => $contact->phone,
                'email' => $contact->email,
                'fax' => $contact->fax,
            ]);
    }

    protected function contactContext(Contact $contact): string
    {
        return match ($contact->type) {
            Contact::TYPE_INSURANCE => 'MCO / Payer',
            Contact::TYPE_PCP => 'Physician / PCP',
            Contact::TYPE_CASE_COORDINATOR => 'Case Coordinator',
            Contact::TYPE_VENDOR, Contact::TYPE_OTHER => $contact->clinic_name ?: 'Portal / Vendor',
            default => $contact->type,
        };
    }

    protected function phoneDigits(string $query): string
    {
        return preg_replace('/\D/', '', $query) ?? '';
    }

    protected function applyPhoneSearch(Builder $query, string $column, string $phoneDigits): void
    {
        $phoneNormalize = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(%s, ''), '(', ''), ')', ''), '-', ''), ' ', ''), '.', '')";

        $query->orWhereRaw(sprintf($phoneNormalize, $column).' LIKE ?', ['%'.$phoneDigits.'%']);
    }
}
