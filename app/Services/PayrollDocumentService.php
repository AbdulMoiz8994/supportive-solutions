<?php

namespace App\Services;

use App\Models\PayRecord;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PayrollDocumentService
{
    public function resolveStubPath(PayRecord $record): ?string
    {
        if (! $record->stub_path) {
            return null;
        }

        $stubPath = ltrim(str_replace('\\', '/', $record->stub_path), '/');
        $prefix = trim(config('payroll.storage_stub_prefix', 'payroll/stubs'), '/');

        if (str_contains($stubPath, '..')) {
            return null;
        }

        if (! str_starts_with($stubPath, $prefix.'/') && ! str_starts_with($stubPath, $prefix)) {
            return null;
        }

        $fullPath = storage_path('app/'.$stubPath);

        return is_file($fullPath) ? $fullPath : null;
    }

    public function stubIsAvailable(PayRecord $record): bool
    {
        return (bool) $record->stub_path && $this->resolveStubPath($record) !== null;
    }

    public function downloadFilename(PayRecord $record): string
    {
        $period = str_replace(' ', '-', (string) $record->period);

        return 'pay-stub-'.$period.'.pdf';
    }

    public function downloadResponse(PayRecord $record): BinaryFileResponse
    {
        $path = $this->resolveStubPath($record);

        if (! $path) {
            abort(404);
        }

        return response()->download($path, $this->downloadFilename($record));
    }

    public function assertStubDeletable(PayRecord $record): void
    {
        if ($record->isPaid() && $record->stub_path) {
            throw new \RuntimeException('Paid pay stubs cannot be deleted during retention period.');
        }
    }
}
