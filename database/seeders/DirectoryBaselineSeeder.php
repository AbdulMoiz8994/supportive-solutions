<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\IntegrationCredential;
use App\Services\GlobalSettingsService;
use Database\Seeders\Concerns\SeedsOrganizationContext;
use Illuminate\Database\Seeder;

/**
 * Demo/staging baseline for the Directory + billing config gaps from the
 * July 2026 A-to-Z review (B1–B3, B6). Safe to run on every fresh seed.
 *
 * Does NOT store real portal passwords — only directory structure, demo ASW
 * contacts, MCO plans, state-system references, and the default ASW fallback.
 */
class DirectoryBaselineSeeder extends Seeder
{
    use SeedsOrganizationContext;

    public function run(): void
    {
        $org = $this->organization();
        $location = $this->location();

        $this->seedPayers($org->id, $location->id);
        $this->seedAsws($org->id, $location->id);
        $this->seedStateSystems($org->id, $location->id);
        $this->seedCredentialPlaceholders();
        $this->seedDefaultAswEmail();

        $this->command?->info('Directory baseline seeded (ASWs, MCOs, state systems, ASW fallback).');
    }

    protected function seedPayers(int $orgId, int $locationId): void
    {
        $payers = [
            ['name' => 'Molina Healthcare of Michigan', 'claim_channel' => Contact::CLAIM_CHANNEL_AVAILITY, 'provider_id' => 'MOLINA-MI'],
            ['name' => 'Aetna Better Health (via Availity)', 'claim_channel' => Contact::CLAIM_CHANNEL_AVAILITY, 'provider_id' => 'AETNA-MI'],
            ['name' => 'Meridian Health Plan', 'claim_channel' => Contact::CLAIM_CHANNEL_AVAILITY, 'provider_id' => 'MERIDIAN-MI'],
            ['name' => 'UnitedHealthcare Community Plan', 'claim_channel' => Contact::CLAIM_CHANNEL_AVAILITY, 'provider_id' => 'UHC-MI'],
            ['name' => 'Blue Cross Complete', 'claim_channel' => Contact::CLAIM_CHANNEL_AVAILITY, 'provider_id' => 'BCC-MI'],
        ];

        foreach ($payers as $payer) {
            Contact::withoutGlobalScopes()->updateOrCreate(
                ['organization_id' => $orgId, 'name' => $payer['name'], 'type' => Contact::TYPE_INSURANCE],
                array_merge($payer, [
                    'organization_id' => $orgId,
                    'location_id' => $locationId,
                    'type' => Contact::TYPE_INSURANCE,
                    'phone' => '(517) 555-0100',
                    'email' => strtolower(str_replace([' ', '(', ')', '.'], '', $payer['name'])).'@demo-payer.test',
                    'is_active' => true,
                ])
            );
        }
    }

    protected function seedAsws(int $orgId, int $locationId): void
    {
        $asws = [
            [
                'name' => 'Denise Carter',
                'email' => 'denise.carter@mdhhs.michigan.gov',
                'phone' => '(313) 555-8201',
                'county' => 'Wayne',
                'job_title' => 'Adult Services Worker',
            ],
            [
                'name' => 'Marcus Reed',
                'email' => 'marcus.reed@mdhhs.michigan.gov',
                'phone' => '(248) 555-8202',
                'county' => 'Oakland',
                'job_title' => 'Adult Services Worker',
            ],
            [
                'name' => 'Sandra Ortiz',
                'email' => 'sandra.ortiz@mdhhs.michigan.gov',
                'phone' => '(586) 555-8203',
                'county' => 'Macomb',
                'job_title' => 'Adult Services Worker',
            ],
        ];

        foreach ($asws as $asw) {
            Contact::withoutGlobalScopes()->updateOrCreate(
                ['organization_id' => $orgId, 'email' => $asw['email']],
                array_merge($asw, [
                    'organization_id' => $orgId,
                    'location_id' => $locationId,
                    'type' => Contact::TYPE_AGENCY_STAFF,
                    'clinic_name' => 'MDHHS · DHS Home Help',
                    'is_active' => true,
                ])
            );
        }
    }

    protected function seedStateSystems(int $orgId, int $locationId): void
    {
        $systems = [
            [
                'name' => 'ICHAT',
                'clinic_name' => 'Michigan State Police · annual criminal history',
                'integration_credential_key' => IntegrationCredential::KEY_ICHAT,
                'integration_slug' => 'ichat',
                'owning_agent' => 'background',
            ],
            [
                'name' => 'CHAMPS / MILogin',
                'clinic_name' => 'Provider enrollment · background checks · billing',
                'integration_credential_key' => IntegrationCredential::KEY_CHAMPS,
                'integration_slug' => 'champs',
                'owning_agent' => 'background',
            ],
            [
                'name' => 'Availity',
                'clinic_name' => '837P claim submission · MCO prior auth',
                'integration_credential_key' => IntegrationCredential::KEY_AVAILITY,
                'integration_slug' => 'availity',
                'owning_agent' => 'billing',
            ],
            [
                'name' => 'MDHHS / Sigma Portal',
                'clinic_name' => 'DHS Home Help billing portal',
                'integration_credential_key' => IntegrationCredential::KEY_SIGMA,
                'integration_slug' => 'sigma',
                'owning_agent' => 'billing',
            ],
            [
                'name' => 'HHAeXchange (EVV)',
                'clinic_name' => 'Electronic visit verification · clock in/out',
                'integration_credential_key' => IntegrationCredential::KEY_HHA,
                'integration_slug' => 'hha',
                'owning_agent' => 'evv',
            ],
            [
                'name' => 'SAM.gov · OIG LEIE',
                'clinic_name' => 'Monthly exclusion screening',
                'integration_slug' => 'sam_oig',
                'owning_agent' => 'background',
            ],
        ];

        foreach ($systems as $system) {
            Contact::withoutGlobalScopes()->updateOrCreate(
                ['organization_id' => $orgId, 'name' => $system['name'], 'type' => Contact::TYPE_OTHER],
                array_merge($system, [
                    'organization_id' => $orgId,
                    'location_id' => $locationId,
                    'type' => Contact::TYPE_OTHER,
                    'is_active' => true,
                    'data_flow' => 'Agent RPA',
                    'app_area' => 'Integrations',
                ])
            );
        }
    }

    /**
     * Empty vault rows so each integration appears on the settings tab.
     * Usernames/secrets stay blank until entered in the UI.
     */
    protected function seedCredentialPlaceholders(): void
    {
        foreach (array_keys(IntegrationCredential::supportedKeys()) as $key) {
            IntegrationCredential::query()->firstOrCreate(['key' => $key]);
        }
    }

    protected function seedDefaultAswEmail(): void
    {
        app(GlobalSettingsService::class)->update([
            'billing' => [
                'default_asw_email' => 'asw.homehelp@mdhhs.michigan.gov',
            ],
        ]);
    }
}
