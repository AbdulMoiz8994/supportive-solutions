<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Document;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\Concerns\SeedsOrganizationContext;
use Illuminate\Database\Seeder;

class DocumentSeeder extends Seeder
{
    use SeedsOrganizationContext;

    public function run(): void
    {
        $org = $this->organization();
        $adminUser = User::where('email', 'admin@beydountech.com')->first();
        $staffUser = User::where('email', 'staff@beydountech.com')->first();

        $client = fn (string $memberId) => Client::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('member_id', $memberId)
            ->first();

        $employee = fn (string $email) => Employee::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('email', $email)
            ->first();

        $documents = [
            ['member_id' => 'MD-100001', 'type' => 'client', 'name' => 'Medicaid ID Card', 'path' => 'documents/john_doe_medicaid_id.pdf', 'doc_type' => 'ID', 'category' => 'Medical', 'expires_at' => '2027-12-31', 'verification_status' => 'Verified', 'is_signed' => false, 'uploaded_by' => $adminUser?->id, 'signed_at' => null],
            ['member_id' => 'MD-100001', 'type' => 'client', 'name' => 'Signed Care Agreement', 'path' => 'documents/john_doe_care_agreement.pdf', 'doc_type' => 'Signed Agreement', 'category' => 'Legal', 'expires_at' => '2026-12-31', 'verification_status' => 'Verified', 'is_signed' => true, 'uploaded_by' => $adminUser?->id, 'signed_at' => now()->subDays(10)],
            ['member_id' => 'MD-100002', 'type' => 'client', 'name' => 'Plan of Care', 'path' => 'documents/jane_smith_poc.pdf', 'doc_type' => 'Medical Form', 'category' => 'Medical', 'expires_at' => '2026-07-31', 'verification_status' => 'Verified', 'is_signed' => true, 'uploaded_by' => $staffUser?->id, 'signed_at' => now()->subDays(5)],
            ['member_id' => 'MD-100003', 'type' => 'client', 'name' => 'Physician Order', 'path' => 'documents/robert_johnson_physician.pdf', 'doc_type' => 'Medical Form', 'category' => 'Medical', 'expires_at' => '2026-06-15', 'verification_status' => 'Pending', 'is_signed' => false, 'uploaded_by' => $staffUser?->id, 'signed_at' => null],
            ['member_id' => 'MD-100004', 'type' => 'client', 'name' => 'Emergency Contact Form', 'path' => 'documents/mary_williams_emergency.pdf', 'doc_type' => 'Emergency Form', 'category' => 'General', 'expires_at' => null, 'verification_status' => 'Verified', 'is_signed' => true, 'uploaded_by' => $adminUser?->id, 'signed_at' => now()->subDays(20)],
            ['member_id' => null, 'employee_email' => 'sarah.c@agency.com', 'type' => 'employee', 'name' => 'Background Check', 'path' => 'documents/sarah_connor_bgcheck.pdf', 'doc_type' => 'HR Document', 'category' => 'HR', 'expires_at' => '2027-01-10', 'verification_status' => 'Verified', 'is_signed' => false, 'uploaded_by' => $adminUser?->id, 'signed_at' => null],
            ['member_id' => null, 'employee_email' => 'sarah.c@agency.com', 'type' => 'employee', 'name' => "Driver's License", 'path' => 'documents/sarah_connor_license.pdf', 'doc_type' => 'ID', 'category' => 'HR', 'expires_at' => '2028-05-03', 'verification_status' => 'Verified', 'is_signed' => false, 'uploaded_by' => $adminUser?->id, 'signed_at' => null],
            ['member_id' => null, 'employee_email' => 'mike.r@agency.com', 'type' => 'employee', 'name' => 'CPR Certificate', 'path' => 'documents/mike_rodriguez_cpr.pdf', 'doc_type' => 'Certification', 'category' => 'Medical', 'expires_at' => '2026-11-05', 'verification_status' => 'Verified', 'is_signed' => false, 'uploaded_by' => $staffUser?->id, 'signed_at' => null],
            ['member_id' => null, 'employee_email' => 'angela.t@agency.com', 'type' => 'employee', 'name' => 'Nursing License', 'path' => 'documents/angela_thompson_rn.pdf', 'doc_type' => 'License', 'category' => 'Medical', 'expires_at' => '2026-04-20', 'verification_status' => 'Pending', 'is_signed' => false, 'uploaded_by' => $staffUser?->id, 'signed_at' => null],
        ];

        foreach ($documents as $doc) {
            if ($doc['type'] === 'client') {
                $subject = $client($doc['member_id']);
                $documentableType = Client::class;
            } else {
                $subject = $employee($doc['employee_email']);
                $documentableType = Employee::class;
            }

            if (! $subject) {
                continue;
            }

            Document::updateOrCreate(
                [
                    'organization_id' => $org->id,
                    'documentable_type' => $documentableType,
                    'documentable_id' => $subject->id,
                    'name' => $doc['name'],
                ],
                [
                    'path' => $doc['path'],
                    'disk' => 'local',
                    'mime_type' => 'application/pdf',
                    'file_size' => 102400,
                    'original_filename' => basename($doc['path']),
                    'type' => $doc['doc_type'],
                    'category' => $doc['category'],
                    'expires_at' => $doc['expires_at'],
                    'verification_status' => $doc['verification_status'],
                    'is_signed' => $doc['is_signed'],
                    'uploaded_by' => $doc['uploaded_by'],
                    'signed_at' => $doc['signed_at'],
                ]
            );
        }

        $this->command?->info('Documents seeded.');
    }
}
