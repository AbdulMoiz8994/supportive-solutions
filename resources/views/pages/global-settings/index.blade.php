@extends('layouts.app')

@section('content')
@php
    use App\Support\TabbedPageTitle;

    $defaultTab = request('tab', 'agency-profile');
    $legacyTabMap = [
        'agency' => 'agency-profile',
        'security' => 'security-compliance',
        'retention' => 'security-compliance',
        'billing' => 'programs-rates',
        'flags' => 'security-compliance',
        'workflows' => 'agency-profile',
        'templates' => 'notifications-language',
        'notifications' => 'notifications-language',
        'compliance' => 'security-compliance',
    ];
    $initialTab = $legacyTabMap[$defaultTab] ?? $defaultTab;

    $saveableTabs = ['programs-rates', 'billing-claims', 'security-compliance', 'access-activation', 'ai-automation', 'notifications-language'];
    $tabSubtitles = [
        'agency-profile' => 'Agency-wide configuration · single source of truth',
        'programs-rates' => 'Programs & Rates',
        'integrations' => 'Integrations & Connections',
        'billing-claims' => 'Billing & Claims · submission credentials & test connections',
        'credential-vault' => 'Credential Vault · encrypted logins the RPA agents use',
        'security-compliance' => 'Security & Compliance',
        'access-activation' => 'Access & Activation Codes · invite-only caregiver app',
        'ai-automation' => 'AI & Automation defaults',
        'notifications-language' => 'Notifications & Language',
    ];

    $tabPageTitles = TabbedPageTitle::globalSettingsTabLabels();

    $vaultConfigured = collect($credentialVault)->where('configured', true)->count();
    $integrationsLive = collect($presenter['integrations'] ?? [])->filter(fn ($row) => str_contains(strtolower($row['status'] ?? ''), 'connected'))->count();
    $michRate = number_format((float) ($settings['programs.mich_hourly_rate'] ?? 30), 0);
    $graceDays = (int) ($settings['programs.pay_grace_days'] ?? 10);
    $retentionYears = (int) round(($settings['retention.document_retention_days'] ?? 2555) / 365);

    $summaryStats = [
        ['label' => 'MICH rate', 'value' => '$'.$michRate.'/hr', 'hint' => 'Default billing', 'tone' => 'blue'],
        ['label' => 'Integrations live', 'value' => (string) $integrationsLive, 'hint' => 'Connected now', 'tone' => 'indigo'],
        ['label' => 'Vault configured', 'value' => (string) $vaultConfigured, 'hint' => 'Credential sets', 'tone' => 'cyan'],
        ['label' => 'Pay grace', 'value' => '~'.$graceDays.' days', 'hint' => 'Never bypassed', 'tone' => 'blue'],
        ['label' => 'Retention', 'value' => $retentionYears.' years', 'hint' => 'HIPAA archive', 'tone' => 'indigo'],
    ];
@endphp

<div x-data="{
    activeTab: '{{ $initialTab }}',
    saveableTabs: @js($saveableTabs),
    tabPageTitles: @js($tabPageTitles),
    appName: @js(config('app.name', 'beydountech Home Care')),
    testingSlug: null,
    testFeedback: {},
    integrationStatuses: @js($presenter['integrationStatuses'] ?? []),
    testUrl: @js(route('settings.global.integrations.test')),
    csrfToken: @js(csrf_token()),
    switchTab(id) {
        this.activeTab = id;
        const url = new URL(window.location.href);
        url.searchParams.set('tab', id);
        window.history.replaceState({}, '', url);
        this.syncTitle();
    },
    syncTitle() {
        const label = this.tabPageTitles[this.activeTab] || 'Global Settings';
        document.title = label + ' — Global Settings | ' + this.appName;
    },
    subtitle() {
        const map = @js($tabSubtitles);
        return map[this.activeTab] || 'Agency-wide configuration · single source of truth';
    },
    statusFor(slug, fallback) {
        const row = this.testFeedback[slug];
        if (row?.summary) return row.summary;
        if (row?.display_status) return row.display_status;
        const saved = this.integrationStatuses[slug];
        if (saved?.summary || saved?.message) {
            const when = saved.tested_at ? new Date(saved.tested_at).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '';
            const msg = saved.summary || saved.message;
            return (saved.label || 'Tested') + (when ? ' · ' + when : '') + ' — ' + msg;
        }
        return fallback;
    },
    badgeFor(slug, fallback) {
        return this.testFeedback[slug]?.badge_class || this.integrationStatuses[slug]?.badge || fallback;
    },
    async testIntegration(slug, formEl = null) {
        if (this.testingSlug) return;
        this.testingSlug = slug;
        try {
            const payload = { slug };
            const draft = this.draftCredentialsFromForm(slug, formEl);
            if (draft) {
                payload.draft = draft;
            }
            const response = await fetch(this.testUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Connection test failed.');
            }
            this.testFeedback[slug] = data;
            this.integrationStatuses[slug] = {
                status: data.status,
                message: data.message,
                summary: data.summary,
                badge: data.badge_class,
                tested_at: data.tested_at,
                label: data.status_label,
                latency_ms: data.latency_ms,
                checks: data.checks,
                recommendation: data.recommendation,
            };
        } catch (error) {
            this.testFeedback[slug] = {
                success: false,
                display_status: error.message || 'Connection test failed.',
                badge_class: 'bg-red-50 text-red-700 border border-red-100',
                status_label: 'Error',
            };
        } finally {
            this.testingSlug = null;
        }
    },
    draftCredentialsFromForm(slug, formEl) {
        if (! formEl) {
            return null;
        }

        const form = formEl.tagName === 'FORM' ? formEl : formEl.closest('form');
        if (! form) {
            return null;
        }

        const read = (name) => {
            const field = form.querySelector('[name=\'' + name + '\']');
            return field ? String(field.value || '').trim() : '';
        };

        const metadata = {};
        form.querySelectorAll('[name^=\'credentials[0][metadata][\']').forEach((field) => {
            const match = field.name.match(/^credentials\[0\]\[metadata\]\[(.+)\]$/);
            if (! match) {
                return;
            }
            const value = String(field.value || '').trim();
            if (value !== '') {
                metadata[match[1]] = value;
            }
        });

        const draft = {
            username: read('credentials[0][username]'),
            password: read('credentials[0][password]'),
            api_key: read('credentials[0][api_key]'),
            metadata,
        };

        const hasContent = draft.username || draft.password || draft.api_key || Object.keys(metadata).length > 0;

        return hasContent ? draft : null;
    }
}" class="max-w-[1400px] mx-auto pt-6 px-6 pb-16 font-['Outfit',sans-serif]">

    @include('pages.settings.partials.flash', ['showValidationErrors' => false])

    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 mb-8">
        <div class="space-y-1">
            <h1 class="text-[28px] font-black text-[#1e293b] tracking-tight leading-none">Global Settings</h1>
            <p class="text-sm font-bold text-[#64748b] tracking-wide opacity-80" x-text="subtitle()"></p>
        </div>
        <button
            type="submit"
            form="global-settings-form"
            x-show="saveableTabs.includes(activeTab)"
            x-cloak
            class="bg-[#2563eb] text-white px-8 py-3 rounded-xl text-xs font-black tracking-[0.05em] uppercase shadow-[0_8px_20px_rgba(37,99,235,0.25)] hover:bg-[#1d4ed8] hover:-translate-y-0.5 transition-all active:scale-95">
            Save all changes
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        @foreach($summaryStats as $stat)
            @php
                $toneClass = match ($stat['tone']) {
                    'indigo' => 'bg-indigo-50 text-indigo-600',
                    'cyan' => 'bg-cyan-50 text-cyan-600',
                    default => 'bg-blue-50 text-blue-600',
                };
            @endphp
            <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-[0_4px_20px_rgb(0,0,0,0.02)] hover:shadow-[0_12px_28px_rgb(0,0,0,0.05)] transition-all group cursor-default">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl {{ $toneClass }} flex items-center justify-center shrink-0 shadow-inner group-hover:scale-105 transition-transform duration-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3" stroke-width="2.5"/></svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-black text-[#94a3b8] uppercase tracking-[0.12em] mb-0.5 truncate">{{ $stat['label'] }}</p>
                        <div class="flex items-baseline gap-1.5">
                            <h4 class="text-xl font-black text-[#1e293b] tracking-tighter leading-tight">{{ $stat['value'] }}</h4>
                            <span class="text-[9px] font-black text-blue-500 truncate">{{ $stat['hint'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex flex-col lg:flex-row gap-6 items-start">
        @include('pages.global-settings.partials.nav')

        <div class="flex-1 w-full bg-white border border-slate-100 rounded-[28px] p-7 min-h-[520px] shadow-[0_10px_40px_rgb(0,0,0,0.03)] relative overflow-hidden">
            <div class="absolute -top-16 -right-16 w-64 h-64 bg-blue-50/20 rounded-full blur-[80px] pointer-events-none"></div>

            <div class="relative z-10">
                <div x-show="activeTab === 'agency-profile'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                    @include('pages.global-settings.partials.agency-profile')
                </div>

                <form method="POST" action="{{ route('settings.global.update') }}" id="global-settings-form">
                    @csrf
                    <input type="hidden" name="_tab" x-bind:value="activeTab">

                    <div x-show="activeTab === 'programs-rates'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                        @include('pages.global-settings.partials.programs-rates')
                    </div>

                    <div x-show="activeTab === 'billing-claims'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                        @include('pages.global-settings.partials.billing-claims')
                    </div>

                    <div x-show="activeTab === 'security-compliance'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                        @include('pages.global-settings.partials.security-compliance')
                    </div>

                    <div x-show="activeTab === 'access-activation'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                        @include('pages.global-settings.partials.access-activation')
                    </div>

                    <div x-show="activeTab === 'ai-automation'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                        @include('pages.global-settings.partials.ai-automation')
                    </div>

                    <div x-show="activeTab === 'notifications-language'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                        @include('pages.global-settings.partials.notifications-language')
                    </div>
                </form>

                <div x-show="activeTab === 'integrations'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                    @include('pages.global-settings.partials.integrations')
                </div>

                <div x-show="activeTab === 'credential-vault'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                    @include('pages.global-settings.partials.credential-vault')
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap');
</style>
@endsection
