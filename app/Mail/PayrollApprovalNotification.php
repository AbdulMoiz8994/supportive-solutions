<?php

namespace App\Mail;

use App\Models\PayrollBatch;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayrollApprovalNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PayrollBatch $batch,
        public User $approvedBy,
        public int $readyCount,
        public int $heldCount,
    ) {}

    public function envelope(): Envelope
    {
        $period = $this->batch->period_key ?? 'payroll';

        return new Envelope(
            subject: "Payroll batch approved — {$period} (Batch #{$this->batch->id})",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.payroll-approval-notification',
        );
    }
}
