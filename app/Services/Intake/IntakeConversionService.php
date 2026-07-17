<?php

namespace App\Services\Intake;

use App\Models\CareDetail;
use App\Models\Client;
use App\Models\Intake;
use App\Models\Status;

/**
 * Converts a qualified intake lead into a client chart, carrying wizard
 * demographics, program path, and authorization stub forward.
 */
class IntakeConversionService
{
    /**
     * @return array{client: Client, intake: Intake, care_detail: ?CareDetail}
     */
    public function convert(Intake $intake, bool $activateImmediately = true): array
    {
        if ($intake->converted_client_id) {
            throw new \RuntimeException('This intake has already been converted.');
        }

        $clientStatusName = $activateImmediately ? 'Active' : 'On Hold';
        $clientStatus = Status::where('entity_type', 'Client')
            ->where('name', $clientStatusName)
            ->first();

        $intakeStatus = Status::where('entity_type', 'Intake')->where('name', 'Converted')->first();

        $client = Client::create([
            'first_name' => $intake->first_name,
            'last_name' => $intake->last_name,
            'dob' => $intake->dob,
            'phone' => $intake->phone,
            'email' => $intake->email,
            'member_id' => $intake->member_id,
            'address' => $intake->address,
            'mco_name' => $intake->mco_name,
            'organization_id' => $intake->organization_id,
            'location_id' => $intake->location_id,
            'status_id' => $clientStatus?->id,
            'coverage_type_id' => $intake->coverage_type_id ?? 1,
            // WorkflowQueueService activation cards query status = Hold.
            'status' => $activateImmediately ? 'Active' : 'Hold',
        ]);

        if ($intake->assigned_employee_id) {
            $client->employees()->syncWithoutDetaching([$intake->assigned_employee_id]);
        }

        $careDetail = $this->createProgramAuthorization($client, $intake);

        $intake->update([
            'converted_client_id' => $client->id,
            'status_id' => $intakeStatus?->id,
            'status' => 'Converted',
        ]);

        return [
            'client' => $client,
            'intake' => $intake->fresh(),
            'care_detail' => $careDetail,
        ];
    }

    protected function createProgramAuthorization(Client $client, Intake $intake): ?CareDetail
    {
        $track = $intake->program_track ?: $this->inferTrack($intake);

        if ($track === 'dhs') {
            return CareDetail::create([
                'organization_id' => $client->organization_id,
                'client_id' => $client->id,
                'billing_code' => 'T1019',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonths(6)->toDateString(),
                'hours_per_week' => $intake->hours_per_week ?? 28,
                'status' => 'Active',
            ]);
        }

        if (in_array($track, ['mich', 'ico', 'daaa'], true)) {
            $units = $intake->pa_units ?? (int) round(($intake->hours_per_week ?? 120) * 4);

            return CareDetail::create([
                'organization_id' => $client->organization_id,
                'client_id' => $client->id,
                'billing_code' => 'T1019',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(14)->toDateString(),
                'total_units' => $units,
                'hours_per_week' => round($units / 4, 2),
                'status' => 'Pending',
            ]);
        }

        return null;
    }

    protected function inferTrack(Intake $intake): string
    {
        $program = strtolower((string) ($intake->recommended_program ?? ''));

        if (str_contains($program, 'dhs') || str_contains($program, 'home help')) {
            return 'dhs';
        }

        if (str_contains($program, 'ico')) {
            return 'ico';
        }

        if (str_contains($program, 'daaa') || str_contains($program, 'aaa')) {
            return 'daaa';
        }

        return filled($intake->mco_name) ? 'mich' : 'dhs';
    }
}
