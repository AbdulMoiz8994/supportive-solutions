@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{
    async confirmFormSubmit(event, options) {
        event.preventDefault();
        await this.$store.dialog.confirmSubmit(event.target, options);
    },
}">

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-[28px] font-extrabold text-[#0f172a] tracking-tight leading-tight">Forms</h1>
            <p class="text-[13px] text-[#64748b] mt-1.5">Reusable templates, e-sign workflow, and signed documents filed automatically.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#eff6ff] text-[#1d4ed8] text-[12px] font-semibold">
                Forms / Documentation Agent
            </span>
            @if($canManageForms)
                <form method="POST" action="{{ route('forms.generate-drafts') }}">
                    @csrf
                    <x-ui.btn variant="outline" size="sm" type="submit">Generate agent drafts</x-ui.btn>
                </form>
                <x-ui.btn variant="primary" size="sm" :href="route('forms.templates.create')">New template</x-ui.btn>
            @endif
        </div>
    </div>

    <section>
        <h2 class="text-[15px] font-bold text-[#0f172a] mb-3">Templates</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
            @foreach($templates as $template)
                <div class="rounded-2xl border border-[#e6eef9] bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] {{ empty($template['is_active']) ? 'opacity-70' : '' }}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="text-[14px] font-bold text-[#0f172a]">{{ $template['name'] }}</div>
                        @if(empty($template['is_active']))
                            <span class="text-[10px] font-semibold uppercase text-[#64748b] bg-[#f1f5f9] px-2 py-0.5 rounded">Inactive</span>
                        @endif
                    </div>
                    <div class="text-[12px] text-[#64748b] mt-1">For: {{ $template['target_label'] }}</div>
                    @if($template['description'])
                        <p class="text-[12px] text-[#94a3b8] mt-2">{{ $template['description'] }}</p>
                    @endif
                    @if($template['is_compliance_required'])
                        <span class="inline-block mt-2 text-[10px] font-semibold uppercase text-[#067647] bg-[#ecfdf3] px-2 py-0.5 rounded">Compliance required</span>
                    @endif
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if(!empty($template['is_active']))
                            <x-ui.btn variant="primary" size="sm" :href="$template['fill_url']">Use / Fill out</x-ui.btn>
                        @endif
                        @if($canManageForms)
                            <x-ui.btn variant="outline" size="sm" :href="$template['edit_url']">Edit</x-ui.btn>
                            @if(!empty($template['is_active']))
                                <form method="POST" action="{{ route('forms.templates.deactivate', $template['id']) }}" class="inline"
                                      @submit.prevent="confirmFormSubmit($event, {
                                          title: 'Deactivate this template?',
                                          message: 'It will be hidden from Fill out. Existing submissions are kept.',
                                          confirmLabel: 'Deactivate',
                                          variant: 'danger',
                                      })">
                                    @csrf
                                    <button type="submit" class="text-[12px] font-semibold text-[#dc2626] hover:underline px-1 py-1">Deactivate</button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section>
        <div class="flex flex-col gap-3 mb-3">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <h2 class="text-[15px] font-bold text-[#0f172a]">Filled forms</h2>
                @if($submissions->total() > 0)
                    <p class="text-[12px] text-[#64748b]">
                        Showing {{ $submissions->firstItem() }}–{{ $submissions->lastItem() }}
                        of {{ number_format($submissions->total()) }}
                    </p>
                @endif
            </div>

            <form method="GET" action="{{ route('forms') }}" class="rounded-2xl border border-[#e6eef9] bg-white p-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6 gap-3">
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wide text-[#64748b] mb-1">Status</label>
                        <select name="status" class="w-full rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
                            @foreach($statusOptions as $opt)
                                <option value="{{ $opt['value'] }}" @selected(($filters['status'] ?? '') === $opt['value'])>{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wide text-[#64748b] mb-1">Template</label>
                        <select name="template_id" class="w-full rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
                            <option value="">All templates</option>
                            @foreach($templateFilterOptions as $opt)
                                <option value="{{ $opt['value'] }}" @selected((string) ($filters['template_id'] ?? '') === (string) $opt['value'])>{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wide text-[#64748b] mb-1">Person type</label>
                        <select name="target_type" class="w-full rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
                            @foreach($targetTypeOptions as $opt)
                                <option value="{{ $opt['value'] }}" @selected(($filters['target_type'] ?? '') === $opt['value'])>{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wide text-[#64748b] mb-1">From</label>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                               class="w-full rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wide text-[#64748b] mb-1">To</label>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                               class="w-full rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wide text-[#64748b] mb-1">Search</label>
                        <input type="search" name="search" value="{{ $filters['search'] ?? '' }}"
                               placeholder="Person or form name"
                               class="w-full rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <select name="per_page" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[12px]">
                        @foreach([10, 15, 25, 50] as $size)
                            <option value="{{ $size }}" @selected(($filters['per_page'] ?? 15) === $size)>{{ $size }} / page</option>
                        @endforeach
                    </select>
                    <x-ui.btn variant="primary" size="sm" type="submit">Apply filters</x-ui.btn>
                    <a href="{{ route('forms') }}" class="text-[12px] font-semibold text-[#64748b] hover:text-[#2563eb] px-2 py-1.5">Clear</a>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-[#e6eef9] bg-white overflow-hidden">
            <table class="w-full text-left text-[13px]">
                <thead>
                    <tr class="border-b border-[#e2e8f0] bg-[#f8fbff] text-[11px] uppercase text-[#64748b]">
                        <th class="px-4 py-3">Form</th>
                        <th class="px-4 py-3">Person</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($submissions as $sub)
                        <tr class="border-b border-[#f1f5f9]">
                            <td class="px-4 py-3 font-semibold">{{ $sub['form'] }}</td>
                            <td class="px-4 py-3">{{ $sub['person'] }}</td>
                            <td class="px-4 py-3">{{ $sub['date'] }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold
                                    @if($sub['status'] === 'signed') bg-[#ecfdf3] text-[#067647]
                                    @elseif($sub['status'] === 'awaiting_signature') bg-[#fff7ed] text-[#c2410c]
                                    @elseif($sub['status'] === 'voided') bg-[#fef2f2] text-[#b91c1c]
                                    @elseif($sub['status'] === 'expired') bg-[#fef3c7] text-[#92400e]
                                    @else bg-[#f1f5f9] text-[#475569]
                                    @endif">
                                    {{ $sub['status_label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <a href="{{ $sub['view_url'] }}" class="text-[12px] font-semibold text-[#2563eb] hover:underline">View</a>
                                    @if($canManageForms && $sub['can_edit'])
                                        <a href="{{ $sub['edit_url'] }}" class="text-[12px] font-semibold text-[#475569] hover:text-[#2563eb] hover:underline">Edit</a>
                                    @endif
                                    @if($canManageForms && $sub['can_delete'])
                                        <form method="POST" action="{{ route('forms.submissions.destroy', $sub['id']) }}" class="inline"
                                              @submit.prevent="confirmFormSubmit($event, {
                                                  title: 'Delete this form submission?',
                                                  message: 'This cannot be undone.',
                                                  confirmLabel: 'Delete',
                                                  variant: 'danger',
                                              })">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-[12px] font-semibold text-[#dc2626] hover:underline">Delete</button>
                                        </form>
                                    @endif
                                    @if($sub['download_url'])
                                        <a href="{{ $sub['download_url'] }}" class="text-[12px] font-semibold text-[#64748b] hover:text-[#2563eb] hover:underline">Download</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-[#64748b]">No filled forms match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($submissions->hasPages())
                <div class="px-4 py-3 border-t border-[#f1f5f9] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-[#fafbfc]">
                    <p class="text-[12px] text-[#64748b]">
                        Page {{ $submissions->currentPage() }} of {{ $submissions->lastPage() }}
                    </p>
                    <div>{{ $submissions->links() }}</div>
                </div>
            @endif
        </div>
    </section>
</div>
@endsection
