@php
    $catalog = $presenter['catalog'] ?? config('global_settings');
    $programRules = $presenter['programRules'] ?? [];
    $badgeTone = fn (string $tone) => $tone === 'blue'
        ? 'bg-blue-50 text-blue-700 border border-blue-100'
        : 'bg-purple-50 text-purple-700 border border-purple-100';
@endphp

<div class="space-y-6">
    <x-global-settings.section-card title="Billing rates" subtitle="Defaults applied at billing — editable per claim in Billing & per payer in Directories" :error-keys="['programs', 'programs.mich_hourly_rate', 'programs.dhs_hourly_rate']">
        <x-global-settings.field-row label="MICH rate" hint="MCO-contracted · T1019">
            <div class="flex items-center gap-2">
                <span class="text-sm font-black text-[#1e293b]">$</span>
                <input type="number" step="0.01" name="programs[mich_hourly_rate]" value="{{ old('programs.mich_hourly_rate', $settings['programs.mich_hourly_rate']) }}" class="{{ $settingsInput }} max-w-[140px]">
                <span class="text-sm font-bold text-[#64748b]">/ hr</span>
            </div>
        </x-global-settings.field-row>
        <x-global-settings.field-row label="DHS Home Help rate" hint="state standard">
            <div class="flex items-center gap-2">
                <span class="text-sm font-black text-[#1e293b]">$</span>
                <input type="number" step="0.01" name="programs[dhs_hourly_rate]" value="{{ old('programs.dhs_hourly_rate', $settings['programs.dhs_hourly_rate']) }}" class="{{ $settingsInput }} max-w-[140px]">
                <span class="text-sm font-bold text-[#64748b]">/ hr</span>
            </div>
        </x-global-settings.field-row>
        <x-global-settings.field-row label="Per-payer overrides" hint="set a different MCO rate">
            <a href="{{ route('directory') }}" class="text-[#2563eb] font-black text-xs hover:underline">Manage in Directories ›</a>
        </x-global-settings.field-row>
    </x-global-settings.section-card>

    <x-global-settings.section-card title="Caregiver wage" subtitle="Pay rate — independent of billing rate (W-2)" :error-keys="['programs.default_caregiver_wage', 'programs.employment_type']">
        <x-global-settings.field-row label="Default hourly wage">
            <div class="flex items-center gap-2">
                <span class="text-sm font-black text-[#1e293b]">$</span>
                <input type="number" step="0.01" name="programs[default_caregiver_wage]" value="{{ old('programs.default_caregiver_wage', $settings['programs.default_caregiver_wage']) }}" class="{{ $settingsInput }} max-w-[140px]">
                <span class="text-sm font-bold text-[#64748b]">/ hr</span>
            </div>
        </x-global-settings.field-row>
        <x-global-settings.field-row label="Employment type">
            <select name="programs[employment_type]" class="{{ $settingsSelect }} max-w-[180px]">
                @foreach($catalog['employment_types'] ?? [] as $value => $label)
                    <option value="{{ $value }}" @selected(old('programs.employment_type', $settings['programs.employment_type']) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-global-settings.field-row>
    </x-global-settings.section-card>

    <x-global-settings.section-card title="Payroll & grace rules" subtitle="The anti-fraud window and pay cadence" :error-keys="['programs.pay_grace_days', 'programs.batch_build_day', 'programs.pay_day', 'programs.roll_late_forms']">
        <x-global-settings.field-row label="Pay grace window" hint="between form receipt & payout">
            <div class="flex flex-wrap items-center gap-2">
                <input type="number" name="programs[pay_grace_days]" value="{{ old('programs.pay_grace_days', $settings['programs.pay_grace_days']) }}" class="{{ $settingsInput }} max-w-[100px]">
                <span class="text-sm font-bold text-[#64748b]">days</span>
                <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wide bg-red-50 text-red-700 border border-red-100">Never bypassed</span>
            </div>
        </x-global-settings.field-row>
        <x-global-settings.field-row label="Batch build day">
            <select name="programs[batch_build_day]" class="{{ $settingsSelect }} max-w-[200px]">
                @foreach($catalog['batch_build_days'] ?? [] as $value => $label)
                    <option value="{{ $value }}" @selected(old('programs.batch_build_day', $settings['programs.batch_build_day']) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-global-settings.field-row>
        <x-global-settings.field-row label="Pay day">
            <select name="programs[pay_day]" class="{{ $settingsSelect }} max-w-[200px]">
                @foreach($catalog['pay_days'] ?? [] as $value => $label)
                    <option value="{{ $value }}" @selected(old('programs.pay_day', $settings['programs.pay_day']) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-global-settings.field-row>
        <x-global-settings.field-row label="Late forms">
            <label class="inline-flex items-center gap-2.5 cursor-pointer">
                <input type="hidden" name="programs[roll_late_forms]" value="0">
                <input type="checkbox" name="programs[roll_late_forms]" value="1" @checked(old('programs.roll_late_forms', $settings['programs.roll_late_forms'])) class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-bold text-[#64748b]">Roll to next week's run</span>
            </label>
        </x-global-settings.field-row>
    </x-global-settings.section-card>

    <x-global-settings.section-card title="Program rules" subtitle="How compliance & authorizations behave per program" class="overflow-hidden">
        <x-global-settings.data-table :headers="['Program', 'Compliance basis', 'Auth type', 'Auth expiry', 'Payment']">
            @foreach($programRules as $rule)
                <tr class="border-b border-slate-50 text-sm font-semibold text-[#64748b]">
                    <td class="py-3.5 px-3">
                        <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wide {{ $badgeTone($rule['badge']) }}">{{ $rule['program'] }}</span>
                    </td>
                    <td class="py-3.5 px-3">{{ $rule['compliance_basis'] }}</td>
                    <td class="py-3.5 px-3">{{ $rule['auth_type'] }}</td>
                    <td class="py-3.5 px-3">{{ $rule['auth_expiry'] }}</td>
                    <td class="py-3.5 px-3">{{ $rule['payment'] }}</td>
                </tr>
            @endforeach
        </x-global-settings.data-table>
    </x-global-settings.section-card>
</div>
