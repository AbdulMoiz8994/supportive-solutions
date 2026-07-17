@php
    $billingChannels = $presenter['billingChannels'] ?? [];
    $defaultAsw = old('billing.default_asw_email', $settings['billing.default_asw_email'] ?? '');
    $sigmaUrl = old('billing.sigma_portal_url', $settings['billing.sigma_portal_url'] ?? '');
    $methodBadges = [
        'api' => ['label' => 'API', 'class' => 'bg-emerald-50 text-emerald-700 border border-emerald-100'],
        'rpa' => ['label' => 'RPA', 'class' => 'bg-amber-50 text-amber-700 border border-amber-100'],
    ];
    $defaultBadge = 'bg-slate-100 text-slate-600 border border-slate-200';
@endphp

<div class="space-y-6">
    <x-global-settings.section-card
        title="Billing submission settings"
        subtitle="Dynamic defaults for Generate & submit · overrides .env when saved here"
        error-prefixes="billing">
        <x-global-settings.field-row label="Default ASW email" hint="Fallback when client has no ASW contact · DHS Home Help">
            <input
                type="email"
                name="billing[default_asw_email]"
                value="{{ $defaultAsw }}"
                placeholder="asw@mdhhs.example.gov"
                class="{{ $settingsInput }} max-w-md">
        </x-global-settings.field-row>
        <x-global-settings.field-row label="Sigma portal URL" hint="Optional override · also editable in Credential Vault → Sigma">
            <input
                type="url"
                name="billing[sigma_portal_url]"
                value="{{ $sigmaUrl }}"
                placeholder="https://www.michigan.gov/mdhhs"
                class="{{ $settingsInput }} max-w-lg">
        </x-global-settings.field-row>
    </x-global-settings.section-card>

    <x-global-settings.section-card title="Submission channels" subtitle="Test each connection · credentials stored encrypted in Credential Vault">
        <x-global-settings.data-table :headers="['Channel', 'Purpose', 'Method', 'Status / last test', 'Actions']">
            @foreach($billingChannels as $channel)
                @php
                    $badge = $methodBadges[$channel['method']] ?? $methodBadges['api'];
                    $slug = $channel['slug'];
                @endphp
                <tr class="border-b border-slate-50 text-sm font-semibold text-[#64748b] align-top">
                    <td class="py-3.5 px-3 font-black text-[#1e293b]">{{ $channel['label'] }}</td>
                    <td class="py-3.5 px-3">{{ $channel['purpose'] }}</td>
                    <td class="py-3.5 px-3">
                        <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wide {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                    </td>
                    <td class="py-3.5 px-3 max-w-md">
                        <x-global-settings.integration-test-status
                            :slug="$slug"
                            :fallback-status="$channel['status']"
                            :fallback-badge="$channel['health_badge'] ?? $defaultBadge"
                        />
                    </td>
                    <td class="py-3.5 px-3 whitespace-nowrap">
                        <div class="flex flex-col gap-2 items-start">
                            <button
                                type="button"
                                @click="testIntegration('{{ $slug }}')"
                                :disabled="testingSlug === '{{ $slug }}'"
                                class="inline-flex items-center gap-1.5 bg-[#f0f7ff] text-blue-600 px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wide hover:bg-blue-600 hover:text-white transition-all border border-blue-100/50 disabled:opacity-60">
                                <svg class="w-3.5 h-3.5" :class="testingSlug === '{{ $slug }}' ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                <span x-text="testingSlug === '{{ $slug }}' ? 'Testing…' : 'Test connection'"></span>
                            </button>
                            <button type="button" @click="switchTab('credential-vault')" class="text-[#2563eb] font-black text-xs hover:underline">
                                Vault ›
                            </button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-global-settings.data-table>
    </x-global-settings.section-card>

    <x-global-settings.section-card title="All billing channels" subtitle="Composite test · Availity + Google Workspace + Sigma + ASW email">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-2xl bg-slate-50 border border-slate-100">
            <div class="space-y-2 max-w-xl">
                <x-global-settings.integration-test-status
                    slug="billing-claims"
                    fallback-status="Run a full billing connectivity check"
                    :fallback-badge="$defaultBadge"
                />
            </div>
            <button
                type="button"
                @click="testIntegration('billing-claims')"
                :disabled="testingSlug === 'billing-claims'"
                class="inline-flex items-center justify-center gap-2 bg-[#2563eb] text-white px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wide hover:bg-[#1d4ed8] transition-all shadow-[0_8px_20px_rgba(37,99,235,0.25)] disabled:opacity-60 shrink-0">
                <svg class="w-4 h-4" :class="testingSlug === 'billing-claims' ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <span x-text="testingSlug === 'billing-claims' ? 'Testing all…' : 'Test all billing connections'"></span>
            </button>
        </div>
    </x-global-settings.section-card>
</div>
