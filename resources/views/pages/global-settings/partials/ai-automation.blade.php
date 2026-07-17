@php
    $catalog = $presenter['catalog'] ?? config('global_settings');
    $lockedRules = $presenter['lockedRules'] ?? [];
@endphp

<div class="space-y-6">
    <x-global-settings.section-card title="Global automation guardrails" subtitle="Fleet-wide defaults · per-agent overrides live in Staff & AI Agents" error-prefixes="automation">
        <x-global-settings.field-row label="Miss-rate ceiling" hint="auto-pause & alert if exceeded">
            <div class="flex items-center gap-2">
                <input type="number" step="0.1" name="automation[miss_rate_ceiling]" value="{{ old('automation.miss_rate_ceiling', $settings['automation.miss_rate_ceiling']) }}" class="{{ $settingsInput }} max-w-[100px]">
                <span class="text-sm font-bold text-[#64748b]">%</span>
            </div>
        </x-global-settings.field-row>
        <x-global-settings.field-row label="Default autonomy" hint="for new agents">
            <select name="automation[default_autonomy]" class="{{ $settingsSelect }} max-w-[220px]">
                @foreach($catalog['autonomy_modes'] ?? [] as $value => $label)
                    <option value="{{ $value }}" @selected(old('automation.default_autonomy', $settings['automation.default_autonomy']) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-global-settings.field-row>
        <x-global-settings.field-row label="Approval threshold ($)" hint="claims/pay above this need you">
            <div class="flex items-center gap-2">
                <span class="text-sm font-black text-[#1e293b]">$</span>
                <input type="number" name="automation[approval_threshold]" value="{{ old('automation.approval_threshold', $settings['automation.approval_threshold']) }}" class="{{ $settingsInput }} max-w-[140px]">
            </div>
        </x-global-settings.field-row>
        <x-global-settings.field-row label="Everything to single approver" hint="route all approvals to primary admin">
            <label class="inline-flex items-center gap-2.5 cursor-pointer">
                <input type="hidden" name="automation[single_approver]" value="0">
                <input type="checkbox" name="automation[single_approver]" value="1" @checked(old('automation.single_approver', $settings['automation.single_approver'])) class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            </label>
        </x-global-settings.field-row>
    </x-global-settings.section-card>

    <x-global-settings.section-card title="Hard rules the agents must obey" subtitle="Non-negotiable — locked on">
        @foreach($lockedRules as $rule)
            <x-global-settings.field-row :label="$rule['label']" :hint="$rule['hint'] ?? null">
                <div class="flex items-center gap-2">
                    <span class="w-10 h-6 rounded-full bg-[#2563eb] relative inline-block after:content-[''] after:absolute after:top-0.5 after:right-0.5 after:w-5 after:h-5 after:bg-white after:rounded-full"></span>
                    <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wide bg-slate-100 text-slate-600 border border-slate-200">Locked</span>
                </div>
            </x-global-settings.field-row>
        @endforeach
    </x-global-settings.section-card>
</div>
