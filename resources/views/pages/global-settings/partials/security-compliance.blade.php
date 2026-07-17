@php
    $auditPreview = $presenter['auditPreview'] ?? [];
    $retentionYears = app(\App\Services\GlobalSettingsService::class)->retentionYears();
    $pillGreen = 'inline-flex px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wide bg-emerald-50 text-emerald-700 border border-emerald-100';
@endphp

<div class="space-y-6">
    <x-global-settings.section-card title="HIPAA & data protection" subtitle="PHI safeguards across the platform" :error-keys="['retention', 'retention.document_retention_days', 'security.phi_access_logging']">
        <x-global-settings.field-row label="HIPAA-eligible cloud" hint="under signed BAA">
            <div>
                <span class="{{ $pillGreen }}">Active</span>
                <span class="text-[10px] font-bold text-[#94a3b8] ml-2 uppercase tracking-wide">BAA on file</span>
            </div>
        </x-global-settings.field-row>
        <x-global-settings.field-row label="Encryption">
            <div>
                <span class="{{ $pillGreen }}">At rest & in transit</span>
                <span class="text-[10px] font-bold text-[#94a3b8] ml-2 uppercase tracking-wide">AES-256 / TLS 1.2+</span>
            </div>
        </x-global-settings.field-row>
        <x-global-settings.field-row label="Record retention">
            <div class="flex items-center gap-2">
                <input type="number" name="retention[document_retention_days]" value="{{ old('retention.document_retention_days', $settings['retention.document_retention_days']) }}" class="{{ $settingsInput }} max-w-[120px]">
                <span class="text-sm font-bold text-[#64748b]">days (~{{ $retentionYears }} years)</span>
            </div>
        </x-global-settings.field-row>
        <x-global-settings.field-row label="PHI access logging">
            <label class="inline-flex items-center gap-2.5 cursor-pointer">
                <input type="hidden" name="security[phi_access_logging]" value="0">
                <input type="checkbox" name="security[phi_access_logging]" value="1" @checked(old('security.phi_access_logging', $settings['security.phi_access_logging'])) class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-[10px] font-bold text-[#94a3b8] uppercase tracking-wide">every view/edit logged</span>
            </label>
        </x-global-settings.field-row>
    </x-global-settings.section-card>

    <x-global-settings.section-card title="Access security" subtitle="Who and how people sign in" :error-keys="['security', 'security.require_2fa', 'security.session_timeout_minutes', 'security.ip_restrictions', 'uploads', 'uploads.max_file_size_kb', 'flags', 'flags.maintenance_mode']">
        <x-global-settings.field-row label="Two-factor (2FA)">
            <label class="inline-flex items-center gap-2.5 cursor-pointer">
                <input type="hidden" name="security[require_2fa]" value="0">
                <input type="checkbox" name="security[require_2fa]" value="1" @checked(old('security.require_2fa', $settings['security.require_2fa'])) class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-bold text-[#64748b]">Required for all users</span>
            </label>
        </x-global-settings.field-row>
        <x-global-settings.field-row label="Session timeout">
            <input type="number" name="security[session_timeout_minutes]" value="{{ old('security.session_timeout_minutes', $settings['security.session_timeout_minutes']) }}" min="5" max="480" class="{{ $settingsInput }} max-w-[120px]">
        </x-global-settings.field-row>
        <x-global-settings.field-row label="IP / device restrictions">
            <label class="inline-flex items-center gap-2.5 cursor-pointer">
                <input type="hidden" name="security[ip_restrictions]" value="0">
                <input type="checkbox" name="security[ip_restrictions]" value="1" @checked(old('security.ip_restrictions', $settings['security.ip_restrictions'])) class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-[10px] font-bold text-[#94a3b8] uppercase tracking-wide">optional</span>
            </label>
        </x-global-settings.field-row>
        <x-global-settings.field-row label="Maximum upload size">
            <input type="number" name="uploads[max_file_size_kb]" value="{{ old('uploads.max_file_size_kb', $settings['uploads.max_file_size_kb']) }}" min="512" max="51200" class="{{ $settingsInput }} max-w-[140px]">
        </x-global-settings.field-row>
        <x-global-settings.field-row label="Maintenance mode">
            <label class="inline-flex items-center gap-2.5 cursor-pointer">
                <input type="hidden" name="flags[maintenance_mode]" value="0">
                <input type="checkbox" name="flags[maintenance_mode]" value="1" @checked(old('flags.maintenance_mode', $settings['flags.maintenance_mode'])) class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            </label>
        </x-global-settings.field-row>
    </x-global-settings.section-card>

    <x-global-settings.section-card title="Audit log" subtitle="Immutable record of every action — human or agent" class="overflow-hidden">
        <x-global-settings.data-table :headers="['When', 'Actor', 'Action']">
            @forelse($auditPreview as $entry)
                <tr class="border-b border-slate-50 text-sm font-semibold text-[#64748b]">
                    <td class="py-3.5 px-3">{{ $entry['when'] }}</td>
                    <td class="py-3.5 px-3 font-bold text-[#1e293b]">{{ $entry['actor'] }}</td>
                    <td class="py-3.5 px-3">{{ $entry['action'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="py-8 px-3 text-center text-sm font-bold text-[#94a3b8]">No audit entries yet — agent and staff actions will appear here.</td>
                </tr>
            @endforelse
        </x-global-settings.data-table>
        <div class="px-3 pb-4">
            <a href="{{ route('settings.global.audit-log') }}" class="text-[#2563eb] font-black text-xs hover:underline">View full audit log ›</a>
        </div>
    </x-global-settings.section-card>
</div>
