@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    @include('pages.settings.partials.flash')

    <div>
        <a href="{{ route('reports.index') }}" class="text-[13px] text-[#2563eb] font-semibold hover:underline">‹ Reports</a>
        <h1 class="text-[28px] font-extrabold text-[#0f172a] mt-1">Schedule reports</h1>
        <p class="text-[13px] text-[#64748b]">Deliver reports to your inbox on a recurring cadence</p>
    </div>

    <form method="POST" action="{{ route('reports.schedule.store') }}" class="rounded-xl border border-[#e2e8f0] bg-white p-6 space-y-4">
        @csrf
        <input type="hidden" name="period" value="{{ $period->format('Y-m') }}">

        <div>
            <label class="text-[11px] uppercase text-[#94a3b8] font-semibold">Report</label>
            <select name="report_slug" required class="mt-1 w-full px-3 py-2 border border-[#e2e8f0] rounded-xl text-[13px]">
                @foreach($reports as $slug => $meta)
                    <option value="{{ $slug }}" @selected($reportSlug === $slug)>{{ $meta['name'] ?? $slug }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="text-[11px] uppercase text-[#94a3b8] font-semibold">Frequency</label>
                <select name="frequency" class="mt-1 w-full px-3 py-2 border border-[#e2e8f0] rounded-xl text-[13px]">
                    @foreach(['monthly' => 'Monthly', 'weekly' => 'Weekly', 'quarterly' => 'Quarterly', 'per_run' => 'Per payroll run'] as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-[11px] uppercase text-[#94a3b8] font-semibold">Export format</label>
                <select name="format" class="mt-1 w-full px-3 py-2 border border-[#e2e8f0] rounded-xl text-[13px]">
                    <option value="csv">CSV</option>
                    <option value="xlsx">Excel (XLSX)</option>
                    <option value="pdf">PDF</option>
                </select>
            </div>
        </div>

        <div>
            <label class="text-[11px] uppercase text-[#94a3b8] font-semibold">Recipients</label>
            <input type="text" name="recipients" required value="{{ auth()->user()->email }}"
                   placeholder="email@agency.com, teammate@agency.com"
                   class="mt-1 w-full px-3 py-2 border border-[#e2e8f0] rounded-xl text-[13px]">
            <p class="text-[11px] text-[#94a3b8] mt-1">Comma-separated email addresses</p>
        </div>

        <button type="submit" class="inline-flex items-center px-5 py-2.5 text-[12px] font-semibold text-white bg-[#2563eb] rounded-xl hover:bg-[#1d4ed8]">
            Save schedule
        </button>
    </form>

    @if($schedules->isNotEmpty())
        <div class="rounded-xl border border-[#e2e8f0] bg-white overflow-hidden">
            <div class="px-4 py-3 border-b border-[#f1f5f9] font-semibold text-[#0f172a]">Active schedules</div>
            <ul class="divide-y divide-[#f1f5f9]">
                @foreach($schedules as $schedule)
                    <li class="px-4 py-3 flex items-center justify-between gap-4 text-[13px]">
                        <div>
                            <span class="font-semibold text-[#0f172a]">{{ config('reports.reports.'.$schedule->report_slug.'.name', $schedule->report_slug) }}</span>
                            <span class="text-[#64748b]"> · {{ ucfirst($schedule->frequency) }} · {{ strtoupper($schedule->format) }}</span>
                        </div>
                        <form method="POST" action="{{ route('reports.schedule.destroy', $schedule) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-[12px] font-semibold text-red-600 hover:underline">Remove</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
