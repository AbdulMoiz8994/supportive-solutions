@php
    $queryBase = request()->except('page');
    $isChipActive = function (array $chip) use ($filters) {
        if ($chip['param'] === 'sort' && $chip['value'] === 'clients_count') {
            return ($filters['sort'] ?? null) === 'clients_count';
        }

        $current = $filters[$chip['param']] ?? null;

        return ($chip['value'] === null && blank($current))
            || ($chip['value'] !== null && $current === $chip['value']);
    };
    $footerText = str_replace(
        [':count', ':linked_clients'],
        [$contacts->total(), $categoryClientTotal ?? 0],
        $indexLayout['footer_template'] ?? ':count contacts in this view'
    );
@endphp

<div class="space-y-6">
    @include('pages.directory._alerts')

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-[22px] font-bold leading-tight text-[#0f172a]">Directories</h1>
            <p class="mt-1 text-[13px] text-[#64748b]">All external entities your agency works with · grouped by type</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" disabled
                    class="inline-flex cursor-not-allowed items-center gap-2 rounded-lg border border-[#e2e8f0] bg-white px-3.5 py-2 text-[13px] font-semibold text-[#94a3b8]"
                    title="Export coming soon">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Export
            </button>
            <a href="{{ route('directory.create') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-[#2563eb] px-3.5 py-2 text-[13px] font-semibold text-white transition hover:bg-[#1d4ed8] focus:outline-none focus:ring-2 focus:ring-[#2563eb]/30">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                Add entry
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-8">
        @foreach ($categories as $category)
            @php
                $count = collect($category['types'])->sum(fn ($type) => (int) ($typeCounts[$type] ?? 0));
                $isActive = ($filters['category'] ?? null) === $category['key']
                    || (filled($filters['type'] ?? null) && in_array($filters['type'], $category['types'], true));
                $cardQuery = array_merge($queryBase, ['category' => $category['key'], 'type' => null, 'page' => null]);
            @endphp
            <a href="{{ route('directory', $cardQuery) }}"
               class="flex items-center gap-3 rounded-[10px] border bg-white p-3.5 transition hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-[#2563eb]/20 {{ $isActive ? $category['card_active_border'] : 'border-[#e2e8f0]' }}">
                <div class="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-[10px] {{ $category['card_icon_bg'] }} text-[#334155]">
                    {!! \App\Support\DirectoryCategories::iconSvg($category['icon']) !!}
                </div>
                <div class="min-w-0">
                    <p class="truncate text-[13px] font-semibold text-[#0f172a]">{{ $category['label'] }}</p>
                    <p class="text-[11px] text-[#94a3b8]">{{ $count }} {{ $category['hint'] }}</p>
                </div>
            </a>
        @endforeach
    </div>

    <div class="flex gap-1 overflow-x-auto border-b border-[#e2e8f0]" role="tablist" aria-label="Directory categories">
        @foreach ($categories as $category)
            @php
                $count = collect($category['types'])->sum(fn ($type) => (int) ($typeCounts[$type] ?? 0));
                $isActive = ($filters['category'] ?? null) === $category['key']
                    || (filled($filters['type'] ?? null) && in_array($filters['type'], $category['types'], true));
            @endphp
            <a href="{{ route('directory', array_merge(collect($queryBase)->except(['type', 'page'])->all(), ['category' => $category['key']])) }}"
               role="tab"
               aria-selected="{{ $isActive ? 'true' : 'false' }}"
               class="shrink-0 border-b-2 px-3.5 py-2.5 text-[13px] font-medium transition {{ $isActive ? 'border-[#2563eb] font-semibold text-[#2563eb]' : 'border-transparent text-[#64748b] hover:text-[#334155]' }}">
                {{ $category['tab_label'] }}
                <span class="ml-1 {{ $isActive ? 'text-[#2563eb]' : 'text-[#94a3b8]' }} font-semibold">{{ $count }}</span>
            </a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-[10px] border border-[#e2e8f0] bg-white">
        <div class="flex items-center justify-between border-b border-[#f1f5f9] px-4 py-3.5">
            <div>
                <h2 class="text-[14px] font-semibold text-[#0f172a]">{{ $indexLayout['panel_title'] }}</h2>
                <p class="mt-0.5 text-[12px] text-[#94a3b8]">{{ $indexLayout['panel_subtitle'] }}</p>
            </div>
            @if ($activeCategory)
                <x-ui.pill variant="blue" size="xs">{{ $contacts->total() }} {{ $activeCategory['hint'] }}</x-ui.pill>
            @endif
        </div>

        <form method="GET" action="{{ route('directory') }}" class="flex flex-wrap items-center gap-2 border-b border-[#f1f5f9] px-4 py-3.5" role="search">
            @foreach (collect($queryBase)->only(['category', 'type', 'status', 'claim_channel', 'organization', 'city', 'county', 'sort', 'direction']) as $key => $value)
                @if (filled($value) && ! in_array($key, ['status', 'claim_channel', 'sort', 'direction'], true))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach

            <div class="relative min-w-[180px] flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#94a3b8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" name="search" value="{{ $filters['search'] }}" maxlength="100"
                       placeholder="{{ $indexLayout['filter_placeholder'] }}"
                       class="w-full rounded-lg border border-[#e2e8f0] bg-white py-1.5 pl-9 pr-3 text-[12.5px] text-[#64748b] outline-none transition focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/20">
            </div>

            @foreach ($indexLayout['chips'] as $chip)
                @php
                    $chipActive = $isChipActive($chip);
                    if ($chip['param'] === 'sort' && $chip['value'] === 'clients_count') {
                        $chipHref = route('directory', array_merge(
                            collect($queryBase)->except(['page'])->all(),
                            ['sort' => 'clients_count', 'direction' => 'desc']
                        ));
                    } else {
                        $chipParams = collect($queryBase)->except(['page', $chip['param']])->all();
                        if (filled($chip['value'])) {
                            $chipParams[$chip['param']] = $chip['value'];
                        }
                        $chipHref = route('directory', $chipParams);
                    }
                @endphp
                <a href="{{ $chipHref }}"
                   class="rounded-full border px-2.5 py-1.5 text-[12px] font-medium transition {{ $chipActive ? 'border-[#2563eb] bg-[#2563eb] text-white' : 'border-[#e2e8f0] bg-white text-[#475569] hover:border-[#cbd5e1]' }}">
                    {{ $chip['label'] }}
                </a>
            @endforeach

            @if ($hasFilters)
                <a href="{{ route('directory', collect($queryBase)->only(['category'])->all()) }}"
                   class="rounded-full border border-[#e2e8f0] bg-white px-2.5 py-1.5 text-[12px] font-semibold text-[#475569] hover:border-[#cbd5e1]">Reset</a>
            @endif

            <div class="ml-auto">
                <label for="directory-sort" class="sr-only">Sort contacts</label>
                <select id="directory-sort" name="sort" onchange="this.form.submit()"
                        class="rounded-lg border border-[#e2e8f0] bg-white px-2.5 py-1.5 text-[12.5px] text-[#64748b] outline-none focus:border-[#2563eb]">
                    @foreach ($indexLayout['sort_options'] as $option)
                        <option value="{{ $option['value'] }}" @selected($filters['sort'] === $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        @if (! empty($filterSummary))
            <div class="flex flex-wrap gap-1.5 border-b border-[#f1f5f9] px-4 py-2">
                @foreach ($filterSummary as $label)
                    <x-ui.pill variant="blue" size="xs">{{ $label }}</x-ui.pill>
                @endforeach
            </div>
        @endif

        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full text-left" aria-label="Directory contacts">
                <thead>
                    <tr class="border-b border-[#e2e8f0] bg-[#fcfDFe]">
                        @foreach ($indexLayout['columns'] as $column)
                            <th scope="col" class="px-3.5 py-2.5 text-[11px] font-medium uppercase tracking-wide text-[#94a3b8]">
                                @if (! empty($column['sortable']))
                                    @php
                                        $nextDirection = ($filters['sort'] === $column['key'] && $filters['direction'] === 'asc') ? 'desc' : 'asc';
                                        $sortQuery = array_merge($queryBase, ['sort' => $column['key'], 'direction' => $nextDirection, 'page' => null]);
                                    @endphp
                                    <a href="{{ route('directory', $sortQuery) }}" class="inline-flex items-center gap-1 hover:text-[#2563eb]">
                                        {{ $column['label'] }}
                                        @if ($filters['sort'] === $column['key'])
                                            <span aria-hidden="true">{{ $filters['direction'] === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </a>
                                @else
                                    {{ $column['label'] }}
                                @endif
                            </th>
                        @endforeach
                        <th scope="col" class="px-3.5 py-2.5"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contacts as $contact)
                        @include('pages.directory._index-table-row', ['contact' => $contact, 'tableKey' => $indexLayout['table_key']])
                    @empty
                        <tr>
                            <td colspan="{{ count($indexLayout['columns']) + 1 }}" class="px-4 py-14">
                                @include('pages.directory._empty-state', ['hasFilters' => $hasFilters])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-[#f1f5f9] md:hidden">
            @forelse ($contacts as $contact)
                <a href="{{ route('directory.show', $contact->id) }}" class="block px-4 py-3.5 transition hover:bg-[#f8fafc]">
                    <div class="flex items-start gap-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-[#2563eb] to-[#1e40af] text-[11px] font-bold text-white">{{ $contact->initials() }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[13px] font-semibold text-[#0f172a]">{{ $contact->name }}</span>
                                <x-ui.pill :variant="$contact->is_active ? 'green' : 'gray'" size="xs">{{ $contact->is_active ? 'Active' : 'Inactive' }}</x-ui.pill>
                            </div>
                            @if ($contact->clinic_name)
                                <p class="mt-0.5 text-[12px] text-[#64748b]">{{ $contact->clinic_name }}</p>
                            @endif
                        </div>
                        <span class="text-[12px] font-semibold text-[#2563eb]">Open ›</span>
                    </div>
                </a>
            @empty
                <div class="px-4 py-10">@include('pages.directory._empty-state', ['hasFilters' => $hasFilters])</div>
            @endforelse
        </div>

        <div class="flex flex-col gap-3 border-t border-[#f1f5f9] px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-[12.5px] text-[#64748b]">{{ $footerText }}</p>
            @if ($contacts->hasPages())
                <div>{{ $contacts->links() }}</div>
            @endif
        </div>
    </div>
</div>
