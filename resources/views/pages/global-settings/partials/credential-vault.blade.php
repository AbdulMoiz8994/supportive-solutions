@php
    $vaultRows = $presenter['vaultRows'] ?? [];
    $highlight = request('integration');
    $btnPrimary = 'bg-[#2563eb] text-white px-8 py-3 rounded-xl text-xs font-black uppercase tracking-wide hover:bg-[#1d4ed8] transition-colors shadow-[0_8px_20px_rgba(37,99,235,0.2)]';
    $testBtn = 'inline-flex items-center gap-1.5 bg-[#f0f7ff] text-blue-600 px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wide hover:bg-blue-600 hover:text-white transition-all border border-blue-100/50 disabled:opacity-60';
    $defaultBadge = 'bg-slate-100 text-slate-600 border border-slate-200';
@endphp

<x-global-settings.section-card class="overflow-hidden">
    <div class="p-6 border-b border-slate-50">
        <div class="flex flex-wrap items-center gap-2 mb-1">
            <h3 class="{{ $settingsSectionTitle }}">Encrypted credential vault</h3>
            <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wide bg-emerald-50 text-emerald-700 border border-emerald-100">AES-256 · access-logged</span>
        </div>
        <p class="{{ $settingsSectionDesc }}">Secrets are masked, never shown in full; the RPA agents retrieve them at run time. Every access is audit-logged.</p>
    </div>

    @if(count($vaultRows))
        <x-global-settings.data-table :headers="['System', 'Login', 'Secret', 'Used by', 'Last used / rotated', 'Actions']" class="px-2">
            @foreach($vaultRows as $row)
                <tr class="border-b border-slate-50 text-sm font-semibold text-[#64748b] align-top">
                    <td class="py-3.5 px-3 font-black text-[#1e293b]">{{ $row['system'] }}</td>
                    <td class="py-3.5 px-3 font-mono text-xs">{{ $row['login'] }}</td>
                    <td class="py-3.5 px-3 font-mono text-xs">{{ $row['has_secret'] ? '••••••••' : '—' }}</td>
                    <td class="py-3.5 px-3">{{ $row['used_by'] }}</td>
                    <td class="py-3.5 px-3">{{ $row['last_used'] }} · rot. {{ $row['rotated'] }}</td>
                    <td class="py-3.5 px-3">
                        <div class="flex flex-col gap-2 items-start">
                            <button
                                type="button"
                                @click="testIntegration('{{ $row['key'] }}', document.getElementById('vault-{{ $row['key'] }}'))"
                                :disabled="testingSlug === '{{ $row['key'] }}'"
                                class="{{ $testBtn }}">
                                <svg class="w-3.5 h-3.5" :class="testingSlug === '{{ $row['key'] }}' ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                <span x-text="testingSlug === '{{ $row['key'] }}' ? 'Testing…' : 'Test'"></span>
                            </button>
                            <x-global-settings.integration-test-status
                                :slug="$row['key']"
                                fallback-status=""
                                :fallback-badge="$defaultBadge"
                            />
                            <a href="#vault-{{ $row['key'] }}" class="text-[#2563eb] font-black text-xs hover:underline">Rotate ›</a>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-global-settings.data-table>
    @endif

    <div class="p-6 pt-0">
        <p class="text-xs text-amber-600 font-bold mb-4">API integrations and portal logins are managed below. Leave secret fields blank to keep existing values. When all required fields are filled, <strong>Test connection</strong> uses those values even before you save.</p>
        <div class="space-y-4">
            @foreach($credentialVault as $key => $entry)
                @php
                    $vaultHasErrors = $highlight === $key
                        && old('credentials.0.key', request('integration')) === $key
                        && $errors->any();
                @endphp
                <form method="POST" action="{{ route('settings.global.credential-vault') }}" id="vault-{{ $key }}" @class([
                    'rounded-2xl border p-5 bg-slate-50/40 scroll-mt-24 transition-all block',
                    'border-red-200 ring-2 ring-red-100 shadow-sm' => $vaultHasErrors,
                    'border-blue-200 ring-2 ring-blue-100 shadow-sm' => $highlight === $key && ! $vaultHasErrors,
                    'border-slate-100' => $highlight !== $key,
                ])>
                    @csrf
                    @if(old('credentials.0.key', request('integration')) === $key)
                        <x-global-settings.validation-errors prefixes="credentials.0" class="mb-4" />
                    @endif
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
                        <div>
                            <h4 class="text-base font-black text-[#1e293b]">{{ $entry['label'] }}</h4>
                            <x-global-settings.integration-test-status
                                :slug="$key"
                                fallback-status=""
                                :fallback-badge="$defaultBadge"
                            />
                        </div>
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <button
                                type="button"
                                @click="testIntegration('{{ $key }}', $el.closest('form'))"
                                :disabled="testingSlug === '{{ $key }}'"
                                class="{{ $testBtn }}">
                                <svg class="w-3.5 h-3.5" :class="testingSlug === '{{ $key }}' ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                <span x-text="testingSlug === '{{ $key }}' ? 'Testing…' : 'Test connection'"></span>
                            </button>
                            <span class="text-[9px] font-black uppercase tracking-widest {{ $entry['configured'] ? 'text-emerald-600' : 'text-[#94a3b8]' }}">
                                {{ $entry['configured'] ? 'Configured' : 'Not set' }}
                            </span>
                        </div>
                    </div>
                    <input type="hidden" name="credentials[0][key]" value="{{ $key }}">
                    @include('pages.global-settings.partials.integration-credential-fields', [
                        'key' => $key,
                        'entry' => $entry,
                        'index' => 0,
                    ])
                    <div class="mt-5 flex justify-end">
                        <button type="submit" class="{{ $btnPrimary }} !px-6 !py-2.5">
                            Save {{ $entry['label'] }}
                        </button>
                    </div>
                </form>
            @endforeach
        </div>
    </div>
</x-global-settings.section-card>
