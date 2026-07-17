<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $mailBody
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject ?: 'Client Request',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.client-request',
        );
    }
}
