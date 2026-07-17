@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb pageTitle="My Profile" />

@php
    $u = auth()->user();
    $initials = collect(explode(' ', $u->name))->map(fn($w) => strtoupper($w[0]))->take(2)->join('');
    $roleColors = [
        'Super Administrator' => 'bg-purple-500',
        'Administrator'       => 'bg-blue-500',
        'Operations Staff'    => 'bg-green-500',
        'Employee'            => 'bg-orange-500',
    ];
    $avatarColor = $roleColors[$u->role] ?? 'bg-brand-500';
    $nameParts = explode(' ', $u->name, 2);
    $firstName = $nameParts[0] ?? '';
    $lastName   = $nameParts[1] ?? '';
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6"
     x-data="{ editMode: false }">

    {{-- ── Profile Header Card ── --}}
    <div class="flex flex-col gap-5 mb-6 p-5 rounded-2xl border border-gray-200 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-5">
            {{-- Avatar --}}
            <div class="flex-shrink-0 flex items-center justify-center w-20 h-20 rounded-2xl {{ $avatarColor }} text-white font-bold text-2xl shadow-lg">
                {{ $initials }}
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-800 dark:text-white">{{ $u->name }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $u->email }}</p>
                <div class="flex items-center gap-2 mt-2">
                    @php
                        $roleBadge = [
                            'Super Administrator' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
                            'Administrator'       => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                            'Operations Staff'    => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                            'Employee'            => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
                        ][$u->role] ?? 'bg-gray-100 text-gray-700';
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $roleBadge }}">
                        {{ $u->role }}
                    </span>
                    @if($u->organization)
                        <span class="text-xs text-gray-400 dark:text-gray-500">• {{ $u->organization->name }}</span>
                    @endif
                </div>
            </div>
        </div>
        <button @click="editMode = !editMode"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            <span x-text="editMode ? 'Cancel Edit' : 'Edit Profile'">Edit Profile</span>
        </button>
    </div>

    {{-- ── View Mode ── --}}
    <div x-show="!editMode" class="p-5 border border-gray-200 rounded-2xl dark:border-gray-800">
        <h4 class="text-base font-semibold text-gray-800 dark:text-white mb-5">Account Information</h4>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <p class="text-xs text-gray-400 dark:text-gray-500 mb-1 uppercase tracking-wide font-medium">First Name</p>
                <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $firstName }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 dark:text-gray-500 mb-1 uppercase tracking-wide font-medium">Last Name</p>
                <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $lastName ?: '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 dark:text-gray-500 mb-1 uppercase tracking-wide font-medium">Email Address</p>
                <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $u->email }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 dark:text-gray-500 mb-1 uppercase tracking-wide font-medium">Role</p>
                <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $u->role }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 dark:text-gray-500 mb-1 uppercase tracking-wide font-medium">Organization</p>
                <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $u->organization?->name ?? '— System Level —' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 dark:text-gray-500 mb-1 uppercase tracking-wide font-medium">Member Since</p>
                <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $u->created_at->format('M d, Y') }}</p>
            </div>
        </div>
    </div>

    {{-- ── Edit Mode ── --}}
    <div x-show="editMode" style="display:none" class="p-5 border border-brand-200 rounded-2xl dark:border-brand-800/40">
        <h4 class="text-base font-semibold text-gray-800 dark:text-white mb-5">Edit Profile</h4>
        <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5 uppercase tracking-wide">Full Name *</label>
                    <input type="text" name="name" value="{{ $u->name }}" required
                        class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white transition-all"/>
                    @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5 uppercase tracking-wide">Email Address *</label>
                    <input type="email" name="email" value="{{ $u->email }}" required
                        class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white transition-all"/>
                    @error('email')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5 uppercase tracking-wide">New Password <span class="normal-case font-normal">(optional)</span></label>
                    <input type="password" name="password" placeholder="Leave blank to keep current"
                        class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white transition-all"/>
                    @error('password')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5 uppercase tracking-wide">Confirm New Password</label>
                    <input type="password" name="password_confirmation" placeholder="Repeat new password"
                        class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white transition-all"/>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-500 text-sm font-semibold text-white hover:bg-brand-600 shadow-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Save Changes
                </button>
                <button type="button" @click="editMode = false"
                    class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
