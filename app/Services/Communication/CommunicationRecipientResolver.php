<?php

namespace App\Services\Communication;

use App\Models\Client;
use App\Models\CommunicationTemplate;
use App\Models\Contact;
use App\Models\Employee;
use Illuminate\Validation\ValidationException;

class CommunicationRecipientResolver
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array{
     *     recipient_type: ?string,
     *     recipient_id: ?int,
     *     recipient_name: ?string,
     *     recipient_email: ?string,
     *     recipient_phone: ?string,
     *     recipient_fax: ?string,
     *     contact: ?Contact
     * }
     */
    public function resolve(
        CommunicationTemplate $template,
        ?Client $client = null,
        ?Employee $employee = null,
        array $overrides = []
    ): array {
        $strategy = $template->recipient_strategy;
        $result = [
            'recipient_type' => null,
            'recipient_id' => null,
            'recipient_name' => null,
            'recipient_email' => null,
            'recipient_phone' => null,
            'recipient_fax' => null,
            'contact' => null,
        ];

        if ($strategy === CommunicationTemplate::STRATEGY_MANUAL || $strategy === CommunicationTemplate::STRATEGY_CUSTOM_CONTACT) {
            $result['recipient_email'] = $overrides['recipient_email'] ?? $template->default_recipient;
            $result['recipient_fax'] = $overrides['recipient_fax'] ?? null;
            $result['recipient_phone'] = $overrides['recipient_phone'] ?? null;
            $result['recipient_name'] = $overrides['recipient_name'] ?? null;
        } elseif ($strategy === CommunicationTemplate::STRATEGY_CLIENT_CASE_COORDINATOR) {
            $contact = $client ? $this->resolveCaseCoordinator($client) : null;
            $result = $this->fromContact($contact, $template, $result);
        } elseif ($strategy === CommunicationTemplate::STRATEGY_CLIENT_PCP) {
            $contact = $client ? $this->resolvePcp($client) : null;
            $result = $this->fromContact($contact, $template, $result);
        } elseif ($strategy === CommunicationTemplate::STRATEGY_EMPLOYEE) {
            $target = $employee ?? ($overrides['employee_id'] ? Employee::withoutGlobalScopes()->find($overrides['employee_id']) : null);
            if ($target) {
                $result['recipient_type'] = Employee::class;
                $result['recipient_id'] = $target->id;
                $result['recipient_name'] = trim($target->first_name.' '.$target->last_name);
                $result['recipient_email'] = $target->email;
                $result['recipient_phone'] = $target->phone;
            }
        }

        if (! empty($overrides['recipient_email'])) {
            $result['recipient_email'] = $overrides['recipient_email'];
        }

        if (! empty($overrides['recipient_fax'])) {
            $result['recipient_fax'] = $overrides['recipient_fax'];
        }

        if (! empty($overrides['recipient_phone'])) {
            $result['recipient_phone'] = $overrides['recipient_phone'];
        }

        return $result;
    }

    public function assertResolvable(CommunicationTemplate $template, array $recipient): void
    {
        $errors = [];

        if (in_array($template->channel, [CommunicationTemplate::CHANNEL_EMAIL, CommunicationTemplate::CHANNEL_INTERNAL], true)
            && empty($recipient['recipient_email'])) {
            $errors['recipient_email'] = $this->errorMessage($template, 'email');
        }

        if ($template->channel === CommunicationTemplate::CHANNEL_FAX && empty($recipient['recipient_fax'])) {
            $errors['recipient_fax'] = $this->errorMessage($template, 'fax');
        }

        if ($template->channel === CommunicationTemplate::CHANNEL_SMS && empty($recipient['recipient_phone'])) {
            $errors['recipient_phone'] = $this->errorMessage($template, 'sms');
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function fromContact(?Contact $contact, CommunicationTemplate $template, array $result): array
    {
        if ($contact) {
            $result['recipient_type'] = Contact::class;
            $result['recipient_id'] = $contact->id;
            $result['recipient_name'] = $contact->name;
            $result['recipient_email'] = $contact->email;
            $result['recipient_fax'] = $contact->fax;
            $result['recipient_phone'] = $contact->phone;
            $result['contact'] = $contact;
        } else {
            $result['recipient_email'] = $template->default_recipient;
        }

        return $result;
    }

    protected function errorMessage(CommunicationTemplate $template, string $channel): string
    {
        $label = match ($template->recipient_strategy) {
            CommunicationTemplate::STRATEGY_CLIENT_CASE_COORDINATOR => 'Case Coordinator',
            CommunicationTemplate::STRATEGY_CLIENT_PCP => 'Primary Care Physician (PCP)',
            CommunicationTemplate::STRATEGY_EMPLOYEE => 'employee',
            default => 'recipient',
        };

        return "No {$channel} address is available for the {$label}. Add contact details or provide a manual {$channel}.";
    }

    protected function resolveCaseCoordinator(Client $client): ?Contact
    {
        $client->loadMissing('contacts');

        return $client->contacts->first(function (Contact $contact) {
            $role = strtolower($contact->pivot->role ?? '');

            return str_contains($role, 'coordinator') || $contact->type === Contact::TYPE_CASE_COORDINATOR;
        });
    }

    protected function resolvePcp(Client $client): ?Contact
    {
        $client->loadMissing('contacts');

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
