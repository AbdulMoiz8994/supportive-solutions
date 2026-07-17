<?php

namespace App\Services\Communication;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Employee;
use App\Models\Organization;
use Illuminate\Support\HtmlString;

class CommunicationTemplateRenderService
{
    /**
     * @var array<int, string>
     */
    protected array $defaultAllowedKeys;

    public function __construct()
    {
        $this->defaultAllowedKeys = config('communications.template_variables', []);
    }

    /**
     * @param  array<int, string>|null  $allowedKeys
     */
    public function render(
        ?string $content,
        array $variables,
        ?array $allowedKeys = null,
        bool $escape = true
    ): string {
        if ($content === null || $content === '') {
            return '';
        }

        $allowed = $allowedKeys ?? $this->defaultAllowedKeys;

        $rendered = preg_replace_callback(
            '/\{\{\s*([a-z0-9_.]+)\s*\}\}/i',
            function (array $matches) use ($variables, $allowed, $escape) {
                $key = strtolower($matches[1]);

                if (! in_array($key, $allowed, true)) {
                    return $matches[0];
                }

                $value = (string) ($variables[$key] ?? '');

                return $escape ? e($value) : $value;
            },
            $content
        ) ?? $content;

        return $rendered;
    }

    /**
     * @return array<string, string>
     */
    public function buildVariables(
        ?Client $client = null,
        ?Employee $employee = null,
        ?Contact $caseCoordinator = null,
        ?Contact $pcp = null,
        ?Organization $organization = null
    ): array {
        $organization ??= $client?->organization ?? $employee?->organization;

        if ($client && ! $caseCoordinator) {
            $caseCoordinator = $this->findCaseCoordinator($client);
        }

        if ($client && ! $pcp) {
            $pcp = $this->findPcp($client);
        }

        return [
            'client.first_name' => (string) ($client?->first_name ?? ''),
            'client.last_name' => (string) ($client?->last_name ?? ''),
            'client.member_id' => (string) ($client?->member_id ?? ''),
            'client.pcp_name' => (string) ($pcp?->name ?? ''),
            'case_coordinator.name' => (string) ($caseCoordinator?->name ?? ''),
            'employee.name' => $employee ? trim($employee->first_name.' '.$employee->last_name) : '',
            'agency.name' => (string) ($organization?->name ?? config('app.name', 'Home Care EMR')),
        ];
    }

    public function preview(?string $content, array $variables, ?array $allowedKeys = null): HtmlString
    {
        return new HtmlString($this->render($content, $variables, $allowedKeys, true));
    }

    protected function findCaseCoordinator(Client $client): ?Contact
    {
        return $client->contacts->first(function (Contact $contact) {
            $role = strtolower($contact->pivot->role ?? '');

            return str_contains($role, 'coordinator') || $contact->type === Contact::TYPE_CASE_COORDINATOR;
        });
    }

    protected function findPcp(Client $client): ?Contact
    {
        return $client->contacts->first(function (Contact $contact) {
            $role = strtolower($contact->pivot->role ?? '');
            $type = strtolower($contact->type ?? '');

            return $contact->type === Contact::TYPE_PCP
                || str_contains($type, 'physician')
                || str_contains($role, 'pcp')
                || str_contains($role, 'physician');
        });
    }
}
