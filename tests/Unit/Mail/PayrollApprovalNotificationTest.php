<?php

use App\Mail\PayrollApprovalNotification;
use App\Models\PayrollBatch;
use App\Models\User;

test('payroll approval notification uses expected subject and text view', function () {
    $batch = new PayrollBatch([
        'period_key' => '2026-05',
        'approval_status' => 'approved',
    ]);
    $batch->id = 99;
    $approver = new User(['name' => 'Admin User', 'email' => 'admin@example.com']);

    $mail = new PayrollApprovalNotification($batch, $approver, 12, 2);

    expect($mail->envelope()->subject)->toBe('Payroll batch approved — 2026-05 (Batch #99)')
        ->and($mail->content()->text)->toBe('mail.payroll-approval-notification')
        ->and($mail->readyCount)->toBe(12)
        ->and($mail->heldCount)->toBe(2)
        ->and($mail->approvedBy->name)->toBe('Admin User');
});
