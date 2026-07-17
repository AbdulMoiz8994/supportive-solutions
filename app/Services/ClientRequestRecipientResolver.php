<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contact;
use App\Models\RequestTemplate;

class ClientRequestRecipientResolver
{
    public function resolve(Client $client, RequestTemplate $template, array $overrides = []): array
    {
        $recipientType = $template->recipient_type;
        $email = null;
        $fax = null;
        $coordinatorId = null;
        $contactName = null;

        if (in_array($recipientType, [RequestTemplate::RECIPIENT_CUSTOM, RequestTemplate::RECIPIENT_OTHER], true)) {
            $email = $overrides['recipient_email'] ?? $template->default_recipient_email;
            $fax = $overrides['recipient_fax'] ?? $template->default_recipient_fax;
        } elseif ($recipientType === RequestTemplate::RECIPIENT_CASE_COORDINATOR) {
            $contact = $this->resolveCaseCoordinator($client);
            $email = $contact?->email ?? $template->default_recipient_email;
            $fax = $contact?->fax ?? $template->default_recipient_fax;
            $coordinatorId = $contact?->id;
            $contactName = $contact?->name;
        } elseif ($recipientType === RequestTemplate::RECIPIENT_PCP) {
            $contact = $this->resolvePrimaryCarePhysician($client);
            $email = $contact?->email ?? $template->default_recipient_email;
            $fax = $contact?->fax ?? $template->default_recipient_fax;
            $coordinatorId = $contact?->id;
            $contactName = $contact?->name;
        }

        if (! empty($overrides['recipient_email'])) {
            $email = $overrides['recipient_email'];
        }

        if (! empty($overrides['recipient_fax'])) {
            $fax = $overrides['recipient_fax'];
        }

        return [
            'recipient_type' => $recipientType,
            'recipient_email' => $email,
            'recipient_fax' => $fax,
            'coordinator_id' => $coordinatorId,
            'contact_name' => $contactName,
        ];
    }

    public function requiresEmail(RequestTemplate $template): bool
    {
        return in_array($template->delivery_method, [
            RequestTemplate::DELIVERY_EMAIL,
            RequestTemplate::DELIVERY_BOTH,
        ], true);
    }

    public function requiresFax(RequestTemplate $template): bool
    {
        return in_array($template->delivery_method, [
            RequestTemplate::DELIVERY_FAX,
            RequestTemplate::DELIVERY_BOTH,
        ], true);
    }

    protected function resolveCaseCoordinator(Client $client): ?Contact
    {
        return $client->contacts->first(function (Contact $contact) {
            $role = strtolower($contact->pivot->role ?? '');

            return str_contains($role, 'coordinator') || $contact->type === 'Case Coordinator';
        });
    }

    protected function resolvePrimaryCarePhysician(Client $client): ?Contact
    {
        return $client->contacts->first(function (Contact $contact) {
            $role = strtolower($contact->pivot->role ?? '');
            $type = strtolower($contact->type ?? '');

            return $contact->type === 'PCP'
                || str_contains($type, 'physician')
                || str_contains($role, 'pcp')
                || str_contains($role, 'physician');
        });
    }
}
