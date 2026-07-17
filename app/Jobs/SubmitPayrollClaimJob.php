<?php

namespace App\Jobs;

use App\Models\PayRecord;
use App\Models\PayrollClaim;
use App\Services\Payroll\PayrollClaimService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class SubmitPayrollClaimJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $payRecordId
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(PayrollClaimService $claimService): void
    {
        $record = PayRecord::withoutGlobalScopes()->find($this->payRecordId);

        if (! $record) {
            Log::channel('availity')->warning('SubmitPayrollClaimJob skipped — pay record not found', [
                'pay_record_id' => $this->payRecordId,
            ]);

            return;
        }

        if (! $claimService->shouldSubmitViaAvaility($record)) {
            Log::channel('availity')->info('SubmitPayrollClaimJob skipped — not routed through Availity', [
                'pay_record_id' => $record->id,
                'program_tag'   => $record->program_tag,
            ]);

            return;
        }

        try {
            $claimService->submitForPayRecord($record);
        } catch (ValidationException $exception) {
            $this->markFailed($record, collect($exception->errors())->flatten()->implode(' '));

            Log::channel('availity')->error('Payroll claim validation failed', [
                'pay_record_id' => $record->id,
                'errors'        => $exception->errors(),
            ]);

            $this->fail($exception);
        } catch (InvalidArgumentException $exception) {
            $this->markFailed($record, $exception->getMessage());

            Log::channel('availity')->error('Payroll claim submission failed', [
                'pay_record_id' => $record->id,
                'message'       => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('availity')->error('SubmitPayrollClaimJob exhausted retries', [
            'pay_record_id' => $this->payRecordId,
            'message'       => $exception?->getMessage(),
        ]);
    }

    protected function markFailed(PayRecord $record, string $message): void
    {
        $claim = PayrollClaim::withoutGlobalScopes()
            ->where('pay_record_id', $record->id)
            ->latest('id')
            ->first();

        if ($claim) {
            $claim->update([
                'status'        => PayrollClaim::STATUS_FAILED,
                'error_message' => $message,
            ]);

            return;
        }

        PayrollClaim::withoutGlobalScopes()->create([
            'organization_id' => $record->organization_id,
            'pay_record_id'   => $record->id,
            'employee_id'     => $record->employee_id,
            'status'          => PayrollClaim::STATUS_FAILED,
            'error_message'   => $message,
        ]);
    }
}
