@php
    $integrations = $presenter['integrations'] ?? [];
    $methodBadges = [
        'api' => ['label' => 'API', 'class' => 'bg-emerald-50 text-emerald-700 border border-emerald-100'],
        'edi' => ['label' => 'EDI', 'class' => 'bg-emerald-50 text-emerald-700 border border-emerald-100'],
        'rpa' => ['label' => 'RPA', 'class' => 'bg-amber-50 text-amber-700 border border-amber-100'],
        'api_download' => ['label' => 'API / download', 'class' => 'bg-blue-50 text-blue-700 border border-blue-100'],
    ];
    $defaultBadge = 'bg-slate-100 text-slate-600 border border-slate-200';
@endphp

<x-global-settings.section-card title="Connected systems" subtitle="API where available · RPA where the system has no API · all under BAA" class="overflow-hidden">
    <x-global-settings.data-table :headers="['System', 'Purpose', 'Method', 'Status / last sync', 'Actions']">
        @foreach($integrations as $integration)
            @php
                $badge = $methodBadges[$integration['method']] ?? $methodBadges['api'];
                $slug = $integration['slug'] ?? '';
            @endphp
            <tr class="border-b border-slate-50 text-sm font-semibold text-[#64748b] align-top">
                <td class="py-3.5 px-3 font-black text-[#1e293b]">{{ $integration['name'] }}</td>
                <td class="py-3.5 px-3">{{ $integration['purpose'] }}</td>
                <td class="py-3.5 px-3">
                    <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wide {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                </td>
                <td class="py-3.5 px-3 max-w-md">
                    <x-global-settings.integration-test-status
                        :slug="$slug"
                        :fallback-status="$integration['status']"
                        :fallback-badge="$integration['health_badge'] ?? $defaultBadge"
                    />
                </td>
                <td class="py-3.5 px-3 whitespace-nowrap">
                    <div class="flex flex-col gap-2 items-start">
                        @if($integration['testable'] ?? false)
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
                        @endif
                        @if(($integration['manage_tab'] ?? '') === 'credential-vault')
                            <button type="button" @click="switchTab('credential-vault')" class="text-[#2563eb] font-black text-xs hover:underline">
                                {{ $integration['method'] === 'rpa' ? 'Vault ›' : 'Manage ›' }}
                            </button>
                        @elseif(($integration['manage_tab'] ?? '') === 'billing-claims')
                            <button type="button" @click="switchTab('billing-claims')" class="text-[#2563eb] font-black text-xs hover:underline">
                                Billing ›
                            </button>
                        @elseif(!empty($integration['manage_route']))
                            <a href="{{ route($integration['manage_route']) }}" class="text-[#2563eb] font-black text-xs hover:underline">
                                Manage ›
                            </a>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-global-settings.data-table>
</x-global-settings.section-card>
