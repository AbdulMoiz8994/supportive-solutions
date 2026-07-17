<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contact;

class RequestTemplateVariableService
{
    /**
     * @var array<int, string>
     */
    protected array $allowedKeys = [
        'client_name',
        'client_first_name',
        'client_last_name',
        'member_id',
        'dob',
        'case_coordinator_name',
        'pcp_name',
        'agency_name',
    ];

    public function render(?string $content, Client $client, ?Contact $caseCoordinator = null, ?Contact $pcp = null): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        $variables = $this->buildVariables($client, $caseCoordinator, $pcp);

        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', function (array $matches) use ($variables) {
            $key = $matches[1];

            if (! in_array($key, $this->allowedKeys, true)) {
                return $matches[0];
            }

            return $variables[$key] ?? '';
        }, $content) ?? $content;
    }

    /**
     * @return array<string, string>
     */
    public function buildVariables(Client $client, ?Contact $caseCoordinator = null, ?Contact $pcp = null): array
    {
        $caseCoordinator ??= $this->findCaseCoordinator($client);
        $pcp ??= $this->findPcp($client);

        return [
            'client_name' => trim($client->first_name.' '.$client->last_name),
            'client_first_name' => (string) $client->first_name,
            'client_last_name' => (string) ($client->last_name ?? ''),
            'member_id' => (string) ($client->member_id ?? ''),
            'dob' => $client->dob ? (string) $client->dob : '',
            'case_coordinator_name' => (string) ($caseCoordinator?->name ?? ''),
            'pcp_name' => (string) ($pcp?->name ?? ''),
            'agency_name' => (string) ($client->organization?->name ?? config('app.name', 'Home Care EMR')),
        ];
    }

    protected function findCaseCoordinator(Client $client): ?Contact
    {
        return $client->contacts->first(function (Contact $contact) {
            $role = strtolower($contact->pivot->role ?? '');

            return str_contains($role, 'coordinator') || $contact->type === 'Case Coordinator';
        });
    }

    protected function findPcp(Client $client): ?Contact
    {
        return $client->contacts->first(function (Contact $contact) {
            return $contact->type === 'PCP'
                || str_contains(strtolower($contact->type ?? ''), 'physician');
        });
    }
}
