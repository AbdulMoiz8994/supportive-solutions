<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\RequestTemplate;
use Illuminate\Database\Seeder;

class RequestTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->first();

        if (! $organization) {
            $this->command?->warn('RequestTemplateSeeder skipped: organization not found.');

            return;
        }

        $templates = [
            [
                'name' => 'Case Coordinator — POC Update',
                'category' => 'Assessments',
                'delivery_method' => RequestTemplate::DELIVERY_EMAIL,
                'recipient_type' => RequestTemplate::RECIPIENT_CASE_COORDINATOR,
                'subject' => 'POC update request for {{ client_name }}',
                'body' => "Hello {{ case_coordinator_name }},\n\nPlease provide an updated Plan of Care for {{ client_name }} (Member ID: {{ member_id }}, DOB: {{ dob }}).\n\nThank you,\n{{ agency_name }}",
            ],
            [
                'name' => 'PCP — Medical Needs Form',
                'category' => 'Medical',
                'delivery_method' => RequestTemplate::DELIVERY_FAX,
                'recipient_type' => RequestTemplate::RECIPIENT_PCP,
                'subject' => null,
                'body' => "Attention {{ pcp_name }},\n\nPlease complete the medical needs form for {{ client_first_name }} {{ client_last_name }}.\n\n{{ agency_name }}",
            ],
            [
                'name' => 'Custom Follow-up',
                'category' => 'Follow-up',
                'delivery_method' => RequestTemplate::DELIVERY_MANUAL,
                'recipient_type' => RequestTemplate::RECIPIENT_CUSTOM,
                'default_recipient_email' => 'coordination@example.com',
                'subject' => 'Follow-up for {{ client_name }}',
                'body' => "This is a follow-up regarding {{ client_name }}.\n\n{{ agency_name }}",
            ],
        ];

        foreach ($templates as $template) {
            RequestTemplate::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'name' => $template['name'],
                ],
                array_merge($template, [
                    'is_active' => true,
                ])
            );
        }
    }
}
