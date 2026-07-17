<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsOrganizationContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Organization;
use App\Models\Status;
use App\Models\CoverageType;
use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory;

/**
 * Adds realistic, "today"-relative volume so the Live Dashboard looks populated:
 * clients, caregivers, authorizations (varied expiry), current-month billing,
 * past/upcoming visits, documents and a curated recent-activity feed.
 *
 * Uses raw inserts to stay fast and to avoid firing the LogsActivity model
 * events (which would otherwise flood the activity feed with generic rows).
 */
class DashboardDemoSeeder extends Seeder
{
    use SeedsOrganizationContext;

    public function run(): void
    {
        // Idempotency guard — only enrich a thin database.
        if (Client::withoutGlobalScopes()->count() > 30) {
            $this->command?->warn('DashboardDemoSeeder skipped (already enriched).');
            return;
        }

        $faker = Factory::create();

        $org   = $this->organization();
        $orgId = $org->id;
        $now   = Carbon::now();

        $clientActive   = Status::where('entity_type', 'Client')->where('name', 'Active')->value('id');
        $clientInactive = Status::where('entity_type', 'Client')->where('name', 'Inactive')->value('id');
        $empActive      = Status::where('entity_type', 'Employee')->where('name', 'Active')->value('id');
        $coverageId     = CoverageType::first()?->id;
        $admin          = User::where('email', 'admin@beydountech.com')->first();
        $staff          = User::where('email', 'staff@beydountech.com')->first();

        $counties = ['Wayne', 'Oakland', 'Macomb', 'Washtenaw', 'Genesee'];
        $cities   = ['Detroit', 'Dearborn', 'Warren', 'Troy', 'Livonia', 'Sterling Heights', 'Ann Arbor', 'Pontiac', 'Southfield', 'Novi'];

        // ── Clients ──────────────────────────────────────────────────────────
        $clientIds = [];
        $activeClientIds = [];
        for ($i = 1; $i <= 140; $i++) {
            $roll = rand(1, 100);
            $statusStr = $roll <= 80 ? 'Active' : ($roll <= 86 ? 'Hold' : ($roll <= 94 ? 'Pending' : 'Inactive'));
            $first = $faker->firstName();
            $last  = $faker->lastName();

            $id = DB::table('clients')->insertGetId([
                'organization_id'  => $orgId,
                'first_name'       => $first,
                'last_name'        => $last,
                'dob'              => $faker->dateTimeBetween('-95 years', '-60 years')->format('Y-m-d'),
                'email'            => strtolower($first . '.' . $last . $i) . '@example.com',
                'phone'            => '(313) 555-' . str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                'address'          => rand(100, 9999) . ' ' . $faker->streetName() . ', ' . $cities[array_rand($cities)] . ' MI ' . rand(48000, 48999),
                'county'           => $counties[array_rand($counties)],
                'member_id'        => 'MD-3' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'coverage_type_id' => $coverageId,
                'status_id'        => $statusStr === 'Active' ? $clientActive : $clientInactive,
                'status'           => $statusStr,
                'billing_rate'     => [17.00, 17.50, 18.00, 18.50, 19.00, 20.00][array_rand([0, 1, 2, 3, 4, 5])],
                'created_at'       => $now->copy()->subDays(rand(1, 240)),
                'updated_at'       => $now,
            ]);
            $clientIds[] = $id;
            if ($statusStr === 'Active') $activeClientIds[] = $id;
        }

        // ── Caregivers / employees ───────────────────────────────────────────
        $caregiverIds = [];
        for ($i = 1; $i <= 60; $i++) {
            $first = $faker->firstName();
            $last  = $faker->lastName();
            $statusStr = rand(1, 100) <= 85 ? 'Active' : 'On Leave';
            // Two flagged for a background-check review item.
            $bg = ($i <= 2) ? 0 : 1;

            $caregiverIds[] = DB::table('employees')->insertGetId([
                'organization_id'      => $orgId,
                'first_name'           => $first,
                'last_name'            => $last,
                'email'                => strtolower($first . '.' . $last . $i) . '@agency.com',
                'phone'                => '(313) 555-' . str_pad((string) rand(2000, 2999), 4, '0', STR_PAD_LEFT),
                'address'              => rand(100, 9999) . ' ' . $faker->streetName() . ', ' . $cities[array_rand($cities)] . ' MI',
                'position'             => 'Caregiver',
                'status'               => $statusStr,
                'status_id'            => $empActive,
                'hire_date'            => $faker->dateTimeBetween('-4 years', '-1 month')->format('Y-m-d'),
                'has_background_check' => $bg,
                'date_of_birth'        => $faker->dateTimeBetween('-60 years', '-22 years')->format('Y-m-d'),
                'preferred_language'   => ['English', 'Arabic', 'Spanish'][array_rand([0, 1, 2])],
                'created_at'           => $now->copy()->subDays(rand(1, 200)),
                'updated_at'           => $now,
            ]);
        }

        // ── Authorizations (care_details) with varied, current-relative expiry ─
        $auths = [];
        foreach ($activeClientIds as $idx => $cid) {
            if ($idx >= 120) break;
            // Spread end dates around "now": a few expiring soon, a couple expired, most future.
            $offset = [rand(3, 14), rand(15, 30), rand(31, 60), rand(61, 150), rand(150, 300), rand(-12, -1)][array_rand([0, 1, 2, 3, 4, 5])];
            $end    = $now->copy()->addDays($offset);
            $units  = [56, 84, 112, 140, 168][array_rand([0, 1, 2, 3, 4])];
            $auths[] = [
                'organization_id' => $orgId,
                'client_id'       => $cid,
                'billing_code'    => 'T019',
                'start_date'      => $end->copy()->subMonths(6)->format('Y-m-d'),
                'end_date'        => $end->format('Y-m-d'),
                'total_units'     => $units,
                'hours_per_week'  => $units / 4,
                'status'          => $offset < 0 ? 'Expired' : 'Active',
                'authorized_by'   => 'Dr. ' . $faker->lastName(),
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }
        foreach (array_chunk($auths, 100) as $chunk) DB::table('care_details')->insert($chunk);

        // ── Current-month billing ────────────────────────────────────────────
        $billings = [];
        $invNo = 4800;
        foreach ($activeClientIds as $cid) {
            $roll = rand(1, 100);
            $status = $roll <= 40 ? 'Paid' : ($roll <= 65 ? 'Sent' : 'Pending');
            $billings[] = [
                'organization_id' => $orgId,
                'client_id'       => $cid,
                'invoice_number'  => 'INV-' . $now->format('Y') . '-' . str_pad((string) (++$invNo), 4, '0', STR_PAD_LEFT),
                'period_start'    => $now->copy()->startOfMonth()->format('Y-m-d'),
                'period_end'      => $now->copy()->endOfMonth()->format('Y-m-d'),
                'total_amount'    => rand(2800, 12000) / 10,
                'status'          => $status,
                'created_at'      => $now->copy()->subDays(rand(0, 20)),
                'updated_at'      => $now,
            ];
        }
        foreach (array_chunk($billings, 100) as $chunk) DB::table('billings')->insert($chunk);

        // ── Visits: completed (EVV) + a few missed + upcoming scheduled ───────
        $schedules = [];
        for ($i = 0; $i < 180 && !empty($activeClientIds); $i++) {
            $cid = $activeClientIds[array_rand($activeClientIds)];
            $eid = $caregiverIds[array_rand($caregiverIds)];

            if ($i < 150) {
                // Completed in the last 30 days, almost all EVV-verified.
                $date = $now->copy()->subDays(rand(1, 30));
                $schedules[] = $this->visitRow($orgId, $cid, $eid, $date, 'Completed', rand(1, 100) <= 97 ? 1 : 0);
            } elseif ($i < 156) {
                // A handful of missed visits.
                $date = $now->copy()->subDays(rand(1, 30));
                $schedules[] = $this->visitRow($orgId, $cid, $eid, $date, 'Missed', 0);
            } else {
                // Upcoming scheduled (next 10 days).
                $date = $now->copy()->addDays(rand(1, 10));
                $schedules[] = $this->visitRow($orgId, $cid, $eid, $date, 'Scheduled', 0);
            }
        }
        foreach (array_chunk($schedules, 100) as $chunk) DB::table('schedules')->insert($chunk);

        // ── Documents (drives compliance %) ──────────────────────────────────
        $docs = [];
        $docTypes = [['Medicaid ID Card', 'ID'], ['Plan of Care', 'Medical Form'], ['Signed Care Agreement', 'Signed Agreement'], ['Physician Order', 'Medical Form'], ['Emergency Contact Form', 'Emergency Form']];
        foreach (array_slice($clientIds, 0, 90) as $cid) {
            [$dn, $dt] = $docTypes[array_rand($docTypes)];
            $docs[] = [
                'organization_id'     => $orgId,
                'documentable_id'     => $cid,
                'documentable_type'   => 'App\\Models\\Client',
                'name'                => $dn,
                'path'                => 'documents/seed_' . $cid . '.pdf',
                'type'                => $dt,
                'category'            => 'Medical',
                'expires_at'          => $now->copy()->addDays(rand(30, 700))->format('Y-m-d'),
                'verification_status' => rand(1, 100) <= 85 ? 'Verified' : 'Pending',
                'is_signed'           => rand(0, 1),
                'uploaded_by'         => $admin?->id,
                'created_at'          => $now->copy()->subDays(rand(1, 60)),
                'updated_at'          => $now,
            ];
        }
        foreach (array_chunk($docs, 100) as $chunk) DB::table('documents')->insert($chunk);

        // ── Curated recent activity feed ─────────────────────────────────────
        $this->seedActivity($orgId, $clientIds, $billings, $admin, $staff, $now);

        $this->command?->info('✅ DashboardDemoSeeder: ' . count($clientIds) . ' clients, ' . count($caregiverIds) . ' caregivers, ' . count($billings) . ' invoices seeded.');
    }

    private function visitRow($orgId, $cid, $eid, Carbon $date, string $status, int $evv): array
    {
        $start = sprintf('%02d:00:00', rand(7, 15));
        $end   = sprintf('%02d:00:00', (int) substr($start, 0, 2) + rand(2, 4));
        $completed = $status === 'Completed';

        return [
            'organization_id'   => $orgId,
            'client_id'         => $cid,
            'employee_id'       => $eid,
            'date'              => $date->format('Y-m-d'),
            'start_time'        => $start,
            'end_time'          => $end,
            'actual_clock_in'   => $completed ? $date->format('Y-m-d') . ' ' . $start : null,
            'actual_clock_out'  => $completed ? $date->format('Y-m-d') . ' ' . $end : null,
            'total_hours'       => $completed ? (int) substr($end, 0, 2) - (int) substr($start, 0, 2) : null,
            'status'            => $status,
            'evv_status'        => $evv,
            'visit_notes'       => json_encode(['note' => 'Routine personal-care visit.']),
            'created_at'        => $date,
            'updated_at'        => $date,
        ];
    }

    private function seedActivity($orgId, array $clientIds, array $billings, $admin, $staff, Carbon $now): void
    {
        $pick = fn () => $clientIds[array_rand($clientIds)];
        $rows = [
            ['Created Client',  'Client',  $pick(), 'New client intake', 2,   $admin?->id],
            ['Updated Client',  'Client',  $pick(), 'Plan of Care Updated', 9, $staff?->id],
            ['Created Billing', 'Billing', null,    'Billing Finalized', 14,  $admin?->id],
            ['Updated Client',  'Client',  $pick(), 'Eligibility verified', 26, $staff?->id],
            ['Created Schedule','Schedule',null,    'Visit completed', 41,    $staff?->id],
            ['Updated Client',  'Client',  $pick(), 'Authorization renewed', 73, $admin?->id],
        ];

        $descriptions = [
            'New client intake'       => "'s application was successfully added to the records, including initial demographics, contact information, and coverage profile for further review.",
            'Plan of Care Updated'    => "'s care plan was reviewed and verified by the assigned coordinator, with updated service notes and revised support requirements now saved.",
            'Billing Finalized'       => 'Monthly billing batch was completed and prepared for reimbursement, including reviewed service hours, authorization matching, and final submission status.',
            'Eligibility verified'    => "'s Medicaid eligibility was re-verified via the insurance portal and the coverage profile was confirmed for the new authorization period.",
            'Visit completed'         => 'A caregiver visit was clocked out and EVV-verified automatically, with the visit note and hours synced to billing.',
            'Authorization renewed'   => "'s prior authorization (T019) was renewed for a further six-month period after the agent assembled the renewal packet.",
        ];

        $insert = [];
        foreach ($rows as [$action, $subjectType, $subjectId, $title, $minsAgo, $userId]) {
            $namePart = '';
            if ($subjectId) {
                $c = DB::table('clients')->where('id', $subjectId)->first();
                $namePart = $c ? trim($c->first_name . ' ' . $c->last_name) : '';
            }
            $insert[] = [
                'organization_id' => $orgId,
                'user_id'         => $userId,
                'action'          => $action,
                'subject_type'    => 'App\\Models\\' . $subjectType,
                'subject_id'      => $subjectId ?? 0,
                'description'     => $namePart . $descriptions[$title],
                'properties'      => json_encode([]),
                'ip_address'      => '127.0.0.1',
                'created_at'      => $now->copy()->subMinutes($minsAgo),
                'updated_at'      => $now->copy()->subMinutes($minsAgo),
            ];
        }
        DB::table('activity_logs')->insert($insert);
    }
}
