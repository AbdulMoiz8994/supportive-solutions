@php
    $org = $organization;
    $summaries = $presenter['sectionSummaries'] ?? [];
    $sectionLinks = [
        ['tab' => 'programs-rates', 'icon' => '⚖️', 'label' => 'Programs & Rates', 'summary' => $summaries['programs-rates'] ?? ''],
        ['tab' => 'integrations', 'icon' => '🔌', 'label' => 'Integrations', 'summary' => $summaries['integrations'] ?? ''],
        ['tab' => 'credential-vault', 'icon' => '🔑', 'label' => 'Credential Vault', 'summary' => $summaries['credential-vault'] ?? ''],
        ['tab' => 'security-compliance', 'icon' => '🔒', 'label' => 'Security & Compliance', 'summary' => $summaries['security-compliance'] ?? ''],
        ['tab' => 'access-activation', 'icon' => '🎟', 'label' => 'Access & Activation', 'summary' => $summaries['access-activation'] ?? ''],
        ['tab' => 'ai-automation', 'icon' => '🤖', 'label' => 'AI & Automation', 'summary' => $summaries['ai-automation'] ?? ''],
    ];
    $btnPrimary = 'bg-[#2563eb] text-white px-8 py-3 rounded-xl text-xs font-black uppercase tracking-wide hover:bg-[#1d4ed8] transition-colors shadow-[0_8px_20px_rgba(37,99,235,0.2)]';
@endphp

<div class="space-y-6">
    <form method="POST" action="{{ route('settings.global.agency') }}">
        @csrf
        <x-global-settings.section-card title="Agency Profile" subtitle="Legal identity used on claims, invoices and correspondence" :error-keys="['name', 'agency_npi', 'tax_id_ein', 'medicaid_provider_id', 'legal_business_name', 'legal_address_street', 'legal_address_city', 'legal_address_state', 'legal_address_zip', 'main_phone', 'efax_number', 'service_state']">
            <x-global-settings.field-row label="Legal name">
                <input type="text" name="legal_business_name" value="{{ old('legal_business_name', $org?->legal_business_name) }}" class="{{ $settingsInput }}">
            </x-global-settings.field-row>

            <x-global-settings.field-row label="NPI" hint="Type 2 / organizational">
                <input type="text" name="agency_npi" value="{{ old('agency_npi', $org?->agency_npi) }}" class="{{ $settingsInput }} max-w-sm">
            </x-global-settings.field-row>

            <x-global-settings.field-row label="Tax ID (EIN)">
                <input type="text" name="tax_id_ein" value="{{ old('tax_id_ein', $org?->tax_id_ein) }}" placeholder="{{ \App\Services\GlobalSettingsPresenterService::maskTaxId($org?->tax_id_ein) }}" class="{{ $settingsInput }} max-w-sm">
            </x-global-settings.field-row>

            <x-global-settings.field-row label="Medicaid provider ID">
                <input type="text" name="medicaid_provider_id" value="{{ old('medicaid_provider_id', $org?->medicaid_provider_id) }}" class="{{ $settingsInput }} max-w-sm">
            </x-global-settings.field-row>

            <x-global-settings.field-row label="Organization name">
                <input type="text" name="name" value="{{ old('name', $org?->name) }}" class="{{ $settingsInput }}">
            </x-global-settings.field-row>

            <x-global-settings.field-row label="Address" align="start">
                <div class="space-y-2.5">
                    <input type="text" name="legal_address_street" value="{{ old('legal_address_street', $org?->legal_address_street) }}" placeholder="Street" class="{{ $settingsInput }}">
                    <div class="grid grid-cols-3 gap-2.5">
                        <input type="text" name="legal_address_city" value="{{ old('legal_address_city', $org?->legal_address_city) }}" placeholder="City" class="{{ $settingsInput }}">
                        <input type="text" name="legal_address_state" value="{{ old('legal_address_state', $org?->legal_address_state) }}" placeholder="State" maxlength="2" class="{{ $settingsInput }}">
                        <input type="text" name="legal_address_zip" value="{{ old('legal_address_zip', $org?->legal_address_zip) }}" placeholder="ZIP" class="{{ $settingsInput }}">
                    </div>
                </div>
            </x-global-settings.field-row>

            <x-global-settings.field-row label="Main phone / eFax" hint="RingCentral">
                <div class="flex flex-wrap gap-2.5">
                    <input type="text" name="main_phone" value="{{ old('main_phone', $org?->main_phone) }}" placeholder="(313) 555-0100" class="{{ $settingsInput }} max-w-[180px]">
                    <input type="text" name="efax_number" value="{{ old('efax_number', $org?->efax_number) }}" placeholder="eFax (313) 555-0101" class="{{ $settingsInput }} max-w-[200px]">
                </div>
            </x-global-settings.field-row>

            <x-global-settings.field-row label="Service state">
                <div class="flex flex-wrap items-center gap-2.5">
                    <select name="service_state" class="{{ $settingsSelect }} max-w-[200px]">
                        @foreach(config('global_settings.us_states', []) as $code => $label)
                            <option value="{{ $code }}" @selected(old('service_state', $org?->service_state ?? 'MI') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="text-[10px] font-bold text-[#94a3b8] uppercase tracking-wide">single-state · single-tenant</span>
                </div>
            </x-global-settings.field-row>

            <div class="mt-6 pt-2 flex flex-col sm:flex-row sm:items-center gap-3">
                <button type="submit" class="{{ $btnPrimary }}">Save agency identity</button>
                <p class="text-xs text-[#94a3b8] font-bold">HHAeXchange: {{ $hhaConnection['message'] ?? 'Status unknown' }}</p>
            </div>
        </x-global-settings.section-card>
    </form>

    <x-global-settings.section-card title="Settings sections" subtitle="Each section opens its own panel">
        @foreach($sectionLinks as $link)
            <x-global-settings.field-row :label="$link['icon'].' '.$link['label']">
                <div class="text-sm font-semibold text-[#64748b]">
                    {{ $link['summary'] }}
                    <button type="button" @click="switchTab('{{ $link['tab'] }}')" class="ml-2 text-[#2563eb] font-black text-xs hover:underline">Open ›</button>
                </div>
            </x-global-settings.field-row>
        @endforeach
    </x-global-settings.section-card>
</div>
