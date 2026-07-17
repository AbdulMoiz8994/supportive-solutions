@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto pt-6 px-6 pb-16">
    @include('pages.settings.partials.flash')

    <div class="flex items-end justify-between gap-4 mb-8">
        <div>
            <a href="{{ route('settings.global', ['tab' => 'security-compliance']) }}" class="text-[13px] text-[#2563eb] font-semibold hover:underline">‹ Global Settings</a>
            <h1 class="text-[28px] font-black text-[#1e293b] tracking-tight">Audit log</h1>
            <p class="text-sm font-bold text-[#64748b]">Immutable record of every action — human or agent</p>
        </div>
    </div>

    <div class="bg-white border border-slate-100 rounded-[28px] overflow-hidden shadow-sm">
        <table class="w-full text-left">
            <thead>
                <tr class="text-[11px] uppercase tracking-wide text-[#94a3b8] bg-[#fcfdfe] border-b border-[#e2e8f0]">
                    <th class="px-5 py-3">When</th>
                    <th class="px-5 py-3">Actor</th>
                    <th class="px-5 py-3">Source</th>
                    <th class="px-5 py-3">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $entry)
                    <tr class="border-b border-[#f1f5f9] text-[13px]">
                        <td class="px-5 py-3 text-[#64748b]">{{ $entry['when'] }}</td>
                        <td class="px-5 py-3 font-semibold text-[#0f172a]">{{ $entry['actor'] }}</td>
                        <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">{{ $entry['source'] }}</span></td>
                        <td class="px-5 py-3 text-[#334155]">{{ $entry['action'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-[#94a3b8]">No audit entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($entries->hasPages())
        <div class="mt-6">{{ $entries->links() }}</div>
    @endif
</div>
@endsection
