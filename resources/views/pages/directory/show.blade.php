@extends('layouts.app')

@section('content')
<div class="space-y-6">
    @include('pages.directory._alerts')

    <nav aria-label="Breadcrumb" class="text-[12px] font-semibold text-[#2563eb]">
        <a href="{{ route('directory', session('directory.filters', [])) }}" class="hover:text-[#1d4ed8]">‹ Directories</a>
        @if ($showProfile['breadcrumb_label'])
            <span class="text-[#94a3b8]"> · </span>
            <span class="text-[#64748b]">{{ $showProfile['breadcrumb_label'] }}</span>
        @endif
    </nav>

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-[22px] font-bold leading-tight text-[#0f172a]">{{ $contact->name }}</h1>
                @foreach ($showProfile['badges'] as $badge)
                    <x-ui.pill :variant="$badge['variant']" size="sm">{{ $badge['label'] }}</x-ui.pill>
                @endforeach
            </div>
            @if ($showProfile['subtitle'])
                <p class="mt-1 text-[13px] text-[#64748b]">{{ $showProfile['subtitle'] }}</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach ($showProfile['actions'] as $action)
                @if ($action['type'] === 'link')
                    <a href="{{ $action['href'] }}"
                       @if (! empty($action['external'])) target="_blank" rel="noopener noreferrer" @endif
                       class="inline-flex items-center gap-2 rounded-lg border border-[#e2e8f0] bg-white px-3.5 py-2 text-[13px] font-semibold text-[#334155] transition hover:bg-[#f8fafc] focus:outline-none focus:ring-2 focus:ring-[#2563eb]/20">
                        @include('pages.directory._show-icon', ['name' => $action['icon'], 'class' => 'h-4 w-4 ' . ($action['icon'] === 'key' ? 'text-[#eab308]' : ($action['icon'] === 'phone' ? 'text-[#ef4444]' : 'text-[#475569]'))])
                        {{ $action['label'] }}
                    </a>
                @elseif ($action['type'] === 'form')
                    <form method="POST" action="{{ $action['action'] }}">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-lg border border-[#e2e8f0] bg-white px-3.5 py-2 text-[13px] font-semibold text-[#334155] transition hover:bg-[#f8fafc] focus:outline-none focus:ring-2 focus:ring-[#2563eb]/20">
                            @include('pages.directory._show-icon', ['name' => $action['icon'], 'class' => 'h-4 w-4'])
                            {{ $action['label'] }}
                        </button>
                    </form>
                @endif
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_360px]">
        <div class="space-y-4">
            <section class="overflow-hidden rounded-[10px] border border-[#e2e8f0] bg-white">
                <div class="flex items-center gap-3.5 border-b border-[#e2e8f0] bg-[#f8fafc] px-5 py-4">
                    <div class="flex h-[52px] w-[52px] shrink-0 items-center justify-center rounded-xl bg-gradient-to-br {{ $showProfile['avatar_gradient'] ?? 'from-[#2563eb] to-[#1e40af]' }} text-[20px] font-bold text-white">{{ $contact->initials() }}</div>
                    <div>
                        <p class="text-[18px] font-semibold text-[#0f172a]">{{ $contact->name }}</p>
                        <p class="text-[12.5px] text-[#64748b]">{{ $showProfile['profile_subtext'] }}</p>
                    </div>
                </div>

                <div class="space-y-5 px-5 py-5">
                    @foreach ($showProfile['main_sections'] as $index => $section)
                        <div @class(['border-t border-[#f1f5f9] pt-5' => $index > 0])>
                            <h2 class="mb-2.5 text-[12px] font-semibold uppercase tracking-wide text-[#2563eb]">{{ $section['title'] }}</h2>
                            <dl class="grid grid-cols-1 gap-2 sm:grid-cols-[175px_1fr] sm:gap-x-3.5 sm:gap-y-2">
                                @foreach ($section['rows'] as $row)
                                    @include('pages.directory._detail-row', [
                                        'label' => $row['label'],
                                        'value' => $row['value'] ?? null,
                                        'href' => $row['href'] ?? null,
                                        'copyable' => $row['copyable'] ?? false,
                                        'multiline' => $row['multiline'] ?? false,
                                        'linkSuffix' => filled($row['href'] ?? null) ? ' ›' : null,
                                        'inline' => true,
                                    ])
                                @endforeach
                            </dl>
                            @if (! empty($section['maps_url']))
                                <a href="{{ $section['maps_url'] }}" target="_blank" rel="noopener noreferrer"
                                   class="mt-3 inline-flex items-center gap-2 text-[12px] font-semibold text-[#2563eb] hover:underline">
                                    Open in Google Maps ›
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            @if ($showProfile['show_design_note'] ?? false)
                @include('pages.directory._show-design-note')
            @endif
        </div>

        <aside class="space-y-3.5">
            @include('pages.directory._show-sidebar-glance')

            @if (($showProfile['show_linked_clients'] ?? false) && $contact->clients->isNotEmpty())
                <section class="rounded-[10px] border border-[#e2e8f0] bg-white p-4">
                    <h2 class="mb-2.5 text-[12px] font-semibold uppercase tracking-wide text-[#64748b]">{{ $showProfile['linked_clients_title'] ?? 'Linked clients' }}</h2>
                    <ul class="divide-y divide-[#f1f5f9]">
                        @foreach ($contact->clients as $client)
                            <li class="flex items-center justify-between gap-2 py-2 first:pt-0 last:pb-0">
                                <span class="truncate text-[12.5px] text-[#334155]">👤 {{ $client->first_name }} {{ $client->last_name }}</span>
                                <a href="{{ route('clients.show', $client->id) }}" class="shrink-0 text-[12px] font-semibold text-[#2563eb] hover:underline">Open ›</a>
                            </li>
                        @endforeach
                    </ul>
                    @if ($contact->clients_count > $contact->clients->count())
                        <p class="mt-2 text-[12px] font-semibold text-[#2563eb]">+ {{ $contact->clients_count - $contact->clients->count() }} more · View all ›</p>
                    @endif
                </section>
            @endif

            @include('pages.directory._show-related')

            @if (in_array($showProfile['layout'], ['contact'], true))
                <section class="rounded-[10px] border border-[#e2e8f0] bg-white p-4">
                    <h2 class="mb-2.5 text-[12px] font-semibold uppercase tracking-wide text-[#64748b]">Record Information</h2>
                    <dl class="divide-y divide-[#f1f5f9] text-[12.5px]">
                        <div class="flex justify-between gap-3 py-2"><dt class="text-[#64748b]">Created</dt><dd class="font-medium text-[#0f172a]">{{ $contact->created_at?->format('M j, Y') }}</dd></div>
                        <div class="flex justify-between gap-3 py-2"><dt class="text-[#64748b]">Last updated</dt><dd class="font-medium text-[#0f172a]">{{ $contact->updated_at?->format('M j, Y g:i A') }}</dd></div>
                        @if ($createdBy)
                            <div class="flex justify-between gap-3 py-2"><dt class="text-[#64748b]">Created by</dt><dd class="font-medium text-[#0f172a]">{{ $createdBy->name }}</dd></div>
                        @endif
                    </dl>
                    @if ($auditLogs->isNotEmpty())
                        <div class="mt-3 border-t border-[#f1f5f9] pt-3">
                            <h3 class="mb-2 text-[10px] font-bold uppercase tracking-wider text-[#94a3b8]">Recent Activity</h3>
                            <ul class="space-y-2">
                                @foreach ($auditLogs as $log)
                                    <li class="text-[11px] text-[#64748b]">
                                        <span class="font-semibold text-[#334155]">{{ $log->action }}</span>
                                        @if ($log->user)<span>· {{ $log->user->name }}</span>@endif
                                        <span class="block text-[#94a3b8]">{{ $log->created_at?->diffForHumans() }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>
            @endif

            @include('pages.directory._show-danger-zone')
        </aside>
    </div>
</div>
@endsection
