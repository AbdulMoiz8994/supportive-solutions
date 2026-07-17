<?php

namespace App\Mail;

use App\Models\FormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FormEsignRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public FormSubmission $submission,
        public string $signerName,
        public string $signingUrl,
        public string $formName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Please sign: '.$this->formName,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: <<<HTML
                <p>Hello {$this->signerName},</p>
                <p>Please review and electronically sign <strong>{$this->formName}</strong>.</p>
                <p><a href="{$this->signingUrl}">Open &amp; sign form</a></p>
                <p>This secure link expires in 14 days.</p>
            HTML
        );
    }
}
