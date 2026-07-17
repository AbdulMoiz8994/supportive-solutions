@php
    $catalog = $presenter['catalog'] ?? config('global_settings');
    $codes = $presenter['activationCodes'] ?? collect();
    $caregivers = $eligibleCaregivers ?? collect();
    $statusClasses = [
        'green' => 'bg-emerald-50 text-emerald-700 border border-emerald-100',
        'amber' => 'bg-amber-50 text-amber-700 border border-amber-100',
        'gray' => 'bg-slate-100 text-slate-600 border border-slate-200',
        'red' => 'bg-red-50 text-red-700 border border-red-100',
    ];
@endphp

<div class="space-y-6">
    <x-global-settings.section-card title="Caregiver app · activation policy" subtitle="The caregiver app is invite-only — no open sign-up" error-prefixes="access">
        <x-global-settings.field-row label="Sign-up mode">
            <select name="access[signup_mode]" class="{{ $settingsSelect }} max-w-[200px]">
                @foreach($catalog['signup_modes'] ?? [] as $value => $label)
                    <option value="{{ $value }}" @selected(old('access.signup_mode', $settings['access.signup_mode']) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-global-settings.field-row>
        <x-global-settings.field-row label="Code expiry">
            <select name="access[code_expiry_days]" class="{{ $settingsSelect }} max-w-[160px]">
                @foreach($catalog['code_expiry_options'] ?? [] as $value => $label)
                    <option value="{{ $value }}" @selected((int) old('access.code_expiry_days', $settings['access.code_expiry_days']) === (int) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-global-settings.field-row>
        <x-global-settings.field-row label="Bind code to caregiver record" hint="one code = one caregiver">
            <label class="inline-flex items-center gap-2.5 cursor-pointer">
                <input type="hidden" name="access[bind_code_to_caregiver]" value="0">
                <input type="checkbox" name="access[bind_code_to_caregiver]" value="1" @checked(old('access.bind_code_to_caregiver', $settings['access.bind_code_to_caregiver'])) class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            </label>
        </x-global-settings.field-row>
    </x-global-settings.section-card>

    <form method="POST" action="{{ route('settings.global.activation-codes.store') }}" class="rounded-2xl border border-slate-100 bg-slate-50/40 p-5">
        @csrf
        <x-global-settings.validation-errors :keys="['employee_id', 'code']" class="mb-4" />
        <h4 class="text-sm font-black text-[#1e293b] mb-3">Generate activation code</h4>
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[220px]">
                <label class="text-[10px] font-black uppercase text-[#94a3b8]">Caregiver (optional if binding off)</label>
                <select name="employee_id" class="{{ $settingsSelect }} w-full mt-1">
                    <option value="">— Unassigned —</option>
                    @foreach($caregivers as $cg)
                        <option value="{{ $cg->id }}">{{ $cg->first_name }} {{ $cg->last_name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-[#2563eb] text-white px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-wide hover:bg-[#1d4ed8]">
                + Generate code
            </button>
        </div>
    </form>

    <x-global-settings.section-card title="Activation codes" subtitle="Generated at onboarding · single-use" class="overflow-hidden">
        <x-global-settings.data-table :headers="['Code', 'Caregiver', 'Issued', 'Status', '']">
            @forelse($codes as $code)
                <tr class="border-b border-slate-50 text-sm font-semibold text-[#64748b]">
                    <td class="py-3.5 px-3 font-mono text-xs">{{ $code->code }}</td>
                    <td class="py-3.5 px-3 font-black text-[#1e293b]">{{ $code->caregiverName() }}</td>
                    <td class="py-3.5 px-3">{{ optional($code->issued_at)->format('M j') ?? '—' }}</td>
                    <td class="py-3.5 px-3">
                        <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wide {{ $statusClasses[$code->statusBadge()] ?? $statusClasses['gray'] }}">
                            {{ $code->statusLabel() }}
                        </span>
                    </td>
                    <td class="py-3.5 px-3 text-[#2563eb] font-black text-xs">
                        @if($code->status === \App\Models\CaregiverActivationCode::STATUS_PENDING)
                            <form method="POST" action="{{ route('settings.global.activation-codes.resend', $code) }}" class="inline">
                                @csrf
                                <button type="submit" class="hover:underline">Resend ›</button>
                            </form>
                        @elseif($code->status === \App\Models\CaregiverActivationCode::STATUS_EXPIRED)
                            <form method="POST" action="{{ route('settings.global.activation-codes.revoke', $code) }}" class="inline">
                                @csrf
                                <button type="submit" class="hover:underline">Revoke ›</button>
                            </form>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="py-8 px-3 text-center text-sm font-bold text-[#94a3b8]">No activation codes yet — generate one above or during caregiver onboarding.</td>
                </tr>
            @endforelse
        </x-global-settings.data-table>
    </x-global-settings.section-card>
</div>
