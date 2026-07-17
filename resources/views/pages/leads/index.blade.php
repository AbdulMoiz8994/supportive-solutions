@extends('layouts.app')

@section('content')
<div class="px-4 py-4">
    <!-- Header Area -->
    <div class="mb-6">
        <h1 class="text-[20px] font-bold text-gray-900">Intake</h1>
    </div>

    <!-- Main Grid Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" x-data="{ search: '' }"  style="background-color: #eff6ff !important;">
        <!-- Card Header -->
        <div class="px-6 py-5 flex items-center justify-between">
            <h2 class="text-[15px] font-bold text-gray-900">All Intake</h2>
            
            <!-- Search Input -->
            <div class="relative w-72">
                <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" x-model="search" placeholder="Search Intake" 
                       class="w-full bg-white border border-gray-200 rounded-lg py-1.5 pl-9 pr-4 text-[13px] text-gray-900 placeholder-gray-400 focus:ring-0 focus:border-brand-500 transition-all outline-none">
            </div>
        </div>

        <!-- Table Grid -->
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-gray-100" style="background-color: #fff !important;">
                        <th class="px-6 py-3.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Phone</th>
                        <th class="px-6 py-3.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Notes</th>
                        <th class="px-6 py-3.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($leads as $lead)
                    <tr class="hover:bg-gray-50/50 transition-colors" x-show="search === '' || '{{ strtolower($lead->first_name . ' ' . $lead->last_name) }}'.includes(search.toLowerCase())">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-gray-800 flex items-center justify-center text-white text-[11px] font-bold overflow-hidden shadow-sm">
                                    @if(isset($lead->profile_image))
                                        <img src="{{ $lead->profile_image }}" class="w-full h-full object-cover">
                                    @else
                                        <span class="opacity-80">{{ strtoupper(substr($lead->first_name, 0, 1)) }}{{ strtoupper(substr($lead->last_name, 0, 1)) }}</span>
                                    @endif
                                </div>
                                <span class="text-[13px] font-bold text-gray-900 leading-none">{{ $lead->first_name }} {{ $lead->last_name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-[13px] text-gray-600">{{ $lead->phone ?? '123456787890' }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-[13px] text-gray-600">{{ $lead->email ?? 'Dummy@gmail.com' }}</span>
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $statusStyle = match($lead->status) {
                                    'New' => 'bg-blue-50 text-blue-500 border-blue-100',
                                    'Intake Complete' => 'bg-red-50 text-red-400 border-red-100',
                                    default => 'bg-gray-50 text-gray-500 border-gray-100'
                                };
                            @endphp
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-bold border {{ $statusStyle }}">
                                {{ $lead->status ?? 'New' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-[13px] text-gray-500 line-clamp-1">{{ $lead->notes ?? 'Lorem ipsum' }}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button class="p-1.5 text-gray-400 hover:text-gray-900 transition-colors">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"></path></svg>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-20 text-center text-gray-400 text-[13px]">No records found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Footer Pagination -->
        <div class="px-6 py-5 border-t border-gray-50 flex items-center justify-end gap-1.5">
            <button class="p-1.5 rounded-lg border border-gray-100 text-gray-400 hover:bg-gray-50 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </button>
            <button class="w-8 h-8 rounded-lg bg-white border border-gray-100 text-gray-600 text-[12px] font-bold hover:bg-gray-50 transition-all">1</button>
            <button class="w-8 h-8 rounded-lg bg-white border border-gray-100 text-gray-600 text-[12px] font-bold hover:bg-gray-50 transition-all">2</button>
            <button class="w-8 h-8 rounded-lg bg-white border border-gray-100 text-gray-600 text-[12px] font-bold hover:bg-gray-50 transition-all">3</button>
            <button class="w-8 h-8 rounded-lg bg-white border border-gray-100 text-gray-600 text-[12px] font-bold hover:bg-gray-50 transition-all">4</button>
            <button class="p-1.5 rounded-lg border border-gray-100 text-gray-400 hover:bg-gray-50 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </button>
        </div>
    </div>
</div>
@endsection
