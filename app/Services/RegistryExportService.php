<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Employee;
use App\Support\CaregiverStatus;
use App\Support\CsvStream;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistryExportService
{
    public function __construct(
        protected RegistryMetricsService $metrics
    ) {}

    public function exportClients(): StreamedResponse
    {
        $clients = $this->metrics->clients();

        $rows = $clients->map(function (Client $client) {
            $status = $client->statusRecord?->name ?? $client->status ?? 'Active';
            $caregiver = $client->primary_caregiver;

            return [
                trim($client->first_name.' '.$client->last_name),
                $client->member_id,
                $client->county,
                $client->program_label,
                $status,
                $caregiver ? trim($caregiver->first_name.' '.$caregiver->last_name) : '',
                $client->dob ? \Carbon\Carbon::parse($client->dob)->format('m/d/Y') : '',
            ];
        });

        return CsvStream::download(
            'clients-'.now()->format('Y-m-d').'.csv',
            ['Name', 'Medicaid ID', 'County', 'Program', 'Status', 'Primary Caregiver', 'DOB'],
            $rows
        );
    }

    public function exportCaregivers(): StreamedResponse
    {
        $caregivers = $this->metrics->caregivers();

        $rows = $caregivers->map(function (Employee $caregiver) {
            $assignment = $caregiver->assignments->firstWhere('status', 'Active') ?? $caregiver->assignments->first();
            $served = $caregiver->assignments
                ->map(fn ($a) => trim(($a->client?->first_name ?? '').' '.($a->client?->last_name ?? '')))
                ->filter()
                ->unique()
                ->implode(', ');

            return [
                $caregiver->name,
                $caregiver->ssn_last4 ? '***-**-'.$caregiver->ssn_last4 : '',
                CaregiverStatus::normalize($caregiver),
                $caregiver->caregiver_type ?? '',
                $assignment->program ?? 'MICH',
                $served,
                $caregiver->hourly_wage,
            ];
        });

        return CsvStream::download(
            'caregivers-'.now()->format('Y-m-d').'.csv',
            ['Name', 'SSN Last 4', 'Status', 'Type', 'Program', 'Clients Served', 'Hourly Wage'],
            $rows
        );
    }

    public function exportCaregiverAudit(Employee $caregiver): StreamedResponse
    {
        $logs = $caregiver->auditLogs()->orderByDesc('occurred_at')->get();

        $rows = $logs->map(function ($log) {
            $change = collect([$log->value_before, $log->value_after, $log->detail])
                ->filter()
                ->implode(' → ');

            return [
                $log->occurred_at?->format('Y-m-d H:i:s'),
                $log->actor_name,
                $log->actor_role,
                $log->action,
                $log->entity,
                $change,
                $log->source,
            ];
        });

        return CsvStream::download(
            'caregiver-audit-'.$caregiver->id.'-'.now()->format('Y-m-d').'.csv',
            ['Timestamp', 'Actor', 'Role', 'Action', 'Entity', 'Change', 'Source'],
            $rows
        );
    }

    public function exportCaregiverAuditPdf(Employee $caregiver): StreamedResponse
    {
        $logs = $caregiver->auditLogs()->orderByDesc('occurred_at')->get();

        $html = view('pages.caregivers.exports.audit', [
            'caregiver' => $caregiver,
            'logs' => $logs,
        ])->render();

        $filename = 'caregiver-audit-'.$caregiver->id.'-'.now()->format('Y-m-d').'.html';

        return response()->streamDownload(
            fn () => print($html),
            $filename,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }
}
