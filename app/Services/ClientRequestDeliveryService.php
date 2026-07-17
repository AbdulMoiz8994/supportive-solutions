<?php

namespace App\Services;

use App\Mail\ClientRequestMail;
use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\RequestTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ClientRequestDeliveryService
{
    public function __construct(
        protected ClientRequestRecipientResolver $recipientResolver,
        protected RequestTemplateVariableService $variableService
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(Client $client, RequestTemplate $template, User $sender, array $payload = []): ClientRequest
    {
        if ((int) $template->organization_id !== (int) $client->organization_id) {
            throw ValidationException::withMessages([
                'request_template_id' => 'The selected template does not belong to this client organization.',
            ]);
        }

        if (! $template->is_active) {
            throw ValidationException::withMessages([
                'request_template_id' => 'The selected template is inactive.',
            ]);
        }

        $recipient = $this->recipientResolver->resolve($client, $template, $payload);
        $this->assertRecipientAvailable($template, $recipient);

        $subject = $this->variableService->render($template->subject, $client);
        $body = $this->variableService->render($template->body, $client);

        $status = ClientRequest::STATUS_MANUAL;
        $notes = $payload['notes'] ?? null;
        $sentAt = null;

        if ($this->recipientResolver->requiresEmail($template) && ! empty($recipient['recipient_email'])) {
            try {
                Mail::to($recipient['recipient_email'])->send(new ClientRequestMail($subject, $body));
                $status = ClientRequest::STATUS_SENT;
                $sentAt = now();
            } catch (\Throwable $exception) {
                $status = ClientRequest::STATUS_FAILED;
                $notes = trim(($notes ? $notes.' ' : '').'Email delivery failed: '.$exception->getMessage());
            }
        }

        if ($this->recipientResolver->requiresFax($template)) {
            $faxNote = 'Fax delivery pending provider integration.';
            $notes = trim(($notes ? $notes.' ' : '').$faxNote);

            if ($status !== ClientRequest::STATUS_SENT) {
                $status = ClientRequest::STATUS_MANUAL;
            }
        }

        if ($template->delivery_method === RequestTemplate::DELIVERY_MANUAL) {
            $status = ClientRequest::STATUS_MANUAL;
        }

        return ClientRequest::create([
            'organization_id' => $client->organization_id,
            'client_id' => $client->id,
            'request_template_id' => $template->id,
            'sent_by' => $sender->id,
            'coordinator_id' => $recipient['coordinator_id'],
            'template' => $template->name,
            'method' => $this->legacyMethodLabel($template->delivery_method),
            'delivery_method' => $template->delivery_method,
            'recipient_type' => $recipient['recipient_type'],
            'recipient_email' => $recipient['recipient_email'],
            'recipient_fax' => $recipient['recipient_fax'],
            'subject' => $subject,
            'body_snapshot' => $body,
            'notes' => $notes,
            'status' => $status,
            'sent_at' => $sentAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $recipient
     */
    protected function assertRecipientAvailable(RequestTemplate $template, array $recipient): void
    {
        $errors = [];

        if ($this->recipientResolver->requiresEmail($template) && empty($recipient['recipient_email'])) {
            $errors['recipient_email'] = $this->recipientErrorMessage($template->recipient_type, 'email');
        }

        if ($this->recipientResolver->requiresFax($template) && empty($recipient['recipient_fax'])) {
            $errors['recipient_fax'] = $this->recipientErrorMessage($template->recipient_type, 'fax');
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function recipientErrorMessage(string $recipientType, string $channel): string
    {
        $label = match ($recipientType) {
            RequestTemplate::RECIPIENT_CASE_COORDINATOR => 'Case Coordinator',
            RequestTemplate::RECIPIENT_PCP => 'Primary Care Physician (PCP)',
            default => 'recipient',
        };

        return "No {$channel} address is available for the {$label}. Add contact details or provide a manual {$channel}.";
    }

    protected function legacyMethodLabel(string $deliveryMethod): string
    {
        return match ($deliveryMethod) {
            RequestTemplate::DELIVERY_FAX => 'Fax',
            RequestTemplate::DELIVERY_BOTH => 'Email/Fax',
            RequestTemplate::DELIVERY_MANUAL => 'Manual',
            default => 'Email',
        };
    }
}
