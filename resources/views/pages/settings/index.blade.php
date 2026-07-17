@extends('layouts.app')

@section('content')
<div class="max-w-full mx-auto pb-12 space-y-5 font-['Outfit',sans-serif]">

    @include('pages.settings.partials.flash')

    <div>
        <h1 class="text-2xl font-black text-[#1e293b] tracking-tight">Settings</h1>
        <p class="text-sm text-[#64748b] mt-1 font-semibold">Manage users, roles, integrations, and platform configuration.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        @if($isSuperAdmin)
            <a href="{{ route('settings.global') }}" class="rounded-2xl border border-[#e2e8f0] bg-white p-4 shadow-sm hover:border-[#c7d7ee] hover:shadow-md transition-all">
                <p class="text-sm font-black text-[#1e293b]">Global Settings</p>
                <p class="text-xs text-[#64748b] mt-1 font-semibold leading-relaxed">Agency identity, security flags, and platform defaults.</p>
            </a>

            <a href="{{ route('settings.global', ['tab' => 'credential-vault']) }}" class="rounded-2xl border border-[#e2e8f0] bg-white p-4 shadow-sm hover:border-[#c7d7ee] hover:shadow-md transition-all">
                <p class="text-sm font-black text-[#1e293b]">Integration Credentials</p>
                <p class="text-xs text-[#64748b] mt-1 font-semibold leading-relaxed">Availity, AccountantsWorld, HHA, CHAMPS, and RPA agent secrets.</p>
            </a>

            <a href="{{ route('users.index') }}" class="rounded-2xl border border-[#e2e8f0] bg-white p-4 shadow-sm hover:border-[#c7d7ee] hover:shadow-md transition-all">
                <p class="text-sm font-black text-[#1e293b]">Users</p>
                <p class="text-xs text-[#64748b] mt-1 font-semibold leading-relaxed">Platform user accounts, invites, and activation status.</p>
            </a>
        @else
            <a href="{{ route('staff.index') }}" class="rounded-2xl border border-[#e2e8f0] bg-white p-4 shadow-sm hover:border-[#c7d7ee] hover:shadow-md transition-all">
                <p class="text-sm font-black text-[#1e293b]">Users & Staff</p>
                <p class="text-xs text-[#64748b] mt-1 font-semibold leading-relaxed">Manage agency staff accounts, invites, and access.</p>
            </a>
        @endif

        <a href="{{ route('settings.roles') }}" class="rounded-2xl border border-[#e2e8f0] bg-white p-4 shadow-sm hover:border-[#c7d7ee] hover:shadow-md transition-all">
            <p class="text-sm font-black text-[#1e293b]">Roles & Permissions</p>
            <p class="text-xs text-[#64748b] mt-1 font-semibold leading-relaxed">Role templates and permission assignments.</p>
        </a>

        <a href="{{ route('staff.index', ['tab' => 'agents']) }}" class="rounded-2xl border border-[#e2e8f0] bg-white p-4 shadow-sm hover:border-[#c7d7ee] hover:shadow-md transition-all">
            <p class="text-sm font-black text-[#1e293b]">Staff & AI Agents</p>
            <p class="text-xs text-[#64748b] mt-1 font-semibold leading-relaxed">Agent registry, kill switches, scope, credentials, and human staff.</p>
        </a>

        @if($isSuperAdmin)
            <a href="{{ route('locations.index') }}" class="rounded-2xl border border-[#e2e8f0] bg-white p-4 shadow-sm hover:border-[#c7d7ee] hover:shadow-md transition-all">
                <p class="text-sm font-black text-[#1e293b]">Locations</p>
                <p class="text-xs text-[#64748b] mt-1 font-semibold leading-relaxed">Office locations and regional context switching.</p>
            </a>
        @endif
    </div>

    <div class="rounded-2xl border border-[#e2e8f0] bg-[#f8fafc] px-4 py-3 text-xs text-[#64748b] leading-relaxed font-semibold">
        <span class="font-black text-[#475569]">Note:</span> Third-party integration secrets live in
        @if($isSuperAdmin)
            <a href="{{ route('settings.global', ['tab' => 'credential-vault']) }}" class="text-[#2563eb] font-black hover:underline">Credential Vault</a>,
        @else
            <span class="font-black text-[#475569]">Credential Vault</span> (platform administrators),
        @endif
        not in a separate API keys screen.
    </div>
</div>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap');
</style>
@endsection
