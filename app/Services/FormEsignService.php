<?php

namespace App\Services;

use App\Mail\FormEsignRequestMail;
use App\Models\Client;
use App\Models\Employee;
use App\Models\FormSubmission;
use App\Services\Integrations\DocuSignClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * DocuSign-style outbound e-sign: email a secure signing link to the subject,
 * and attempt DocuSign when organization vault credentials hydrate config('docusign.*').
 *
 * Agent catalog `default_credentials` / credential_keys are informational UI/seed
 * metadata only — this service does not read AiAgent credential lists.
 */
class FormEsignService
{
    public function __construct(
        protected DocuSignClient $docuSign,
    ) {}

    public function sendForSignature(FormSubmission $submission): FormSubmission
    {
        $submission->loadMissing(['template', 'subject']);

        $token = $submission->signing_token ?: Str::random(48);
        $channel = 'email_link';
        $externalId = null;

        $subject = $submission->subject;
        $email = $this->subjectEmail($subject);
        $name = $submission->subjectName();
        $formName = $submission->template?->name ?? 'Form';
        $signingUrl = url('/esign/'.$token);

        if ($email) {
            try {
                Mail::to($email)->send(new FormEsignRequestMail($submission, $name, $signingUrl, $formName));
            } catch (\Throwable $e) {
                Log::warning('Form e-sign email failed', [
                    'submission_id' => $submission->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $docuSignResult = ['success' => false];
        if ($email) {
            $docuSignResult = $this->docuSign->createEnvelopeForForm(
                $formName,
                $email,
                $name,
                $signingUrl,
                $this->renderUnsignedPdfBinary($submission),
            );
        }

        if (($docuSignResult['success'] ?? false) === true) {
            $channel = 'docusign';
            $externalId = $docuSignResult['envelope_id'] ?? null;
        }

        $submission->update([
            'status' => FormSubmission::STATUS_AWAITING_SIGNATURE,
            'signing_token' => $token,
            'expires_at' => $submission->expires_at ?? now()->addDays(14),
            'esign_sent_at' => now(),
            'esign_channel' => $channel,
            'esign_external_id' => $externalId,
            'locked_at' => null,
            'fields_snapshot' => $submission->fields_snapshot
                ?: ($submission->template?->fields ?? []),
        ]);

        return $submission->fresh(['template']);
    }

    public function findByToken(string $token): ?FormSubmission
    {
        return FormSubmission::query()
            ->where('signing_token', $token)
            ->where('status', FormSubmission::STATUS_AWAITING_SIGNATURE)
            ->with(['template'])
            ->first();
    }

    public function completeRemoteSign(
        FormSubmission $submission,
        string $signedByName,
        ?string $signatureImage = null,
    ): FormSubmission {
        if ($submission->status !== FormSubmission::STATUS_AWAITING_SIGNATURE) {
            throw new \RuntimeException('This form is not awaiting signature.');
        }

        if ($submission->expires_at && $submission->expires_at->isPast()) {
            $submission->update(['status' => FormSubmission::STATUS_EXPIRED]);
            throw new \RuntimeException('This signing link has expired.');
        }

        $values = $submission->field_values ?? [];
        $values['signature_name'] = $signedByName;
        if ($signatureImage) {
            $values['signature_image'] = $signatureImage;
        }

        $submission->update([
            'field_values' => $values,
            'status' => FormSubmission::STATUS_SIGNED,
            'signed_at' => now(),
            'signed_by_name' => $signedByName,
            'signature_image' => $signatureImage,
            'locked_at' => now(),
            'fields_snapshot' => $submission->fields_snapshot ?: ($submission->template?->fields ?? []),
            'expires_at' => null,
            'signing_token' => null,
        ]);

        app(FormsTrackingService::class)->fileSignedDocument(
            $submission->fresh(['template']),
            $submission->template,
        );

        return $submission->fresh(['template', 'document']);
    }

    private function subjectEmail(mixed $subject): ?string
    {
        if ($subject instanceof Client || $subject instanceof Employee) {
            $email = trim((string) ($subject->email ?? ''));

            return $email !== '' ? $email : null;
        }

        return null;
    }

    /**
     * Render the current form answers as a PDF for DocuSign envelopes.
     * Returns null on failure so the client can fall back to a text stub.
     */
    private function renderUnsignedPdfBinary(FormSubmission $submission): ?string
    {
        try {
            $template = $submission->template;
            if (! $template) {
                return null;
            }

            $fields = $submission->fields_snapshot ?? $template->fields ?? [];

            return \Barryvdh\DomPDF\Facade\Pdf::loadView('pages.forms.pdf', [
                'submission' => $submission,
                'template' => $template,
                'fields' => $fields,
                'subjectName' => $submission->subjectName(),
                'values' => $submission->field_values ?? [],
            ])->setPaper('a4')->output();
        } catch (\Throwable $e) {
            Log::warning('Form e-sign PDF render failed', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
