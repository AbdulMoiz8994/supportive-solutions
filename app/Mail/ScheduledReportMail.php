<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $definition,
        public array $data,
        public string $periodLabel,
        public string $format = 'csv',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->definition['name'] ?? 'Report').' · '.$this->periodLabel,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.scheduled-report',
            with: [
                'reportName' => $this->definition['name'] ?? 'Report',
                'periodLabel' => $this->periodLabel,
                'kpis' => $this->data['kpis'] ?? [],
                'format' => strtoupper($this->format),
            ],
        );
    }
}
