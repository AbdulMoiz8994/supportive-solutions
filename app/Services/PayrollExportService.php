<?php

namespace App\Services;

use App\Models\PayRecord;
use App\Models\User;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollExportService
{
    public function __construct(
        protected PayrollService $payrollService,
        protected PayrollAuditService $auditService
    ) {}

    public function export(?int $organizationId, array $filters, User $actor): StreamedResponse
    {
        $rows = $this->payrollService->exportRows($organizationId, $filters);
        $periodKey = $filters['period'] ?? now()->format('Y-m');
        $filename = 'payroll-'.$periodKey.'.csv';

        $this->auditService->logExport($actor, $organizationId, $periodKey, $rows->count());

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Caregiver', 'Client', 'Period', 'Hours', 'Rate', 'Gross', 'Status']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $this->escapeCsvValue($row->employee?->name),
                    $this->escapeCsvValue(trim(($row->client?->first_name ?? '').' '.($row->client?->last_name ?? ''))),
                    $this->escapeCsvValue($row->period),
                    $row->hours,
                    $row->rate,
                    $row->gross,
                    $this->escapeCsvValue($row->status),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function escapeCsvValue(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $value = (string) $value;

        if (preg_match('/^[=+\-@]/', $value)) {
            return "'".$value;
        }

        return $value;
    }

    public function rowsForTest(?int $organizationId, array $filters): Collection
    {
        return $this->payrollService->exportRows($organizationId, $filters);
    }
}
