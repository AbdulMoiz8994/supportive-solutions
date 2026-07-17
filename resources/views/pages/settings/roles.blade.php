@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto pb-12 space-y-6">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('settings.index') }}" class="text-xs font-bold text-[#2563eb] hover:underline">← Settings</a>
            <h1 class="text-2xl font-black text-[#1e293b] tracking-tight mt-1">Roles & Permissions</h1>
            <p class="text-sm text-[#64748b] mt-1 font-semibold">Review role templates and manage staff access from Staff & AI Agents.</p>
        </div>
        <x-ui.btn href="{{ route('staff.index') }}" variant="primary">Manage staff</x-ui.btn>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($roles as $role)
            <div class="rounded-2xl border border-[#e2e8f0] bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-black text-[#1e293b]">{{ $role->name }}</p>
                        <p class="text-xs text-[#64748b] mt-1 font-semibold">{{ $role->permissions_count }} permissions · {{ $role->users_count }} users</p>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-[#94a3b8]">{{ $role->slug }}</span>
                </div>
            </div>
        @endforeach
    </div>

    <div class="rounded-2xl border border-[#e2e8f0] bg-white overflow-hidden shadow-sm">
        <div class="px-5 py-4 border-b border-[#e2e8f0]">
            <h2 class="text-sm font-black text-[#1e293b]">Permission modules</h2>
        </div>
        <div class="divide-y divide-[#f1f5f9]">
            @foreach($modules as $module => $permissions)
                <div class="px-5 py-4">
                    <p class="text-xs font-black uppercase tracking-wider text-[#94a3b8] mb-2">{{ $module ?: 'General' }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($permissions as $permission)
                            <span class="px-2.5 py-1 rounded-full bg-[#eff6ff] text-[#2563eb] text-[11px] font-bold">{{ $permission->name }}</span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
