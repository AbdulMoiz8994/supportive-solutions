@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb :pageTitle="'Permissions Management: ' . $user->role" />

<div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] overflow-hidden">
    <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-800">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Role: {{ $user->role }}</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Configure what users with this role can see and do across the portal.</p>
    </div>

    <form action="{{ route('roles.permissions.update', $user->roleModel->id) }}" method="POST">
        @csrf
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($modules as $module => $perms)
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 p-4">
                        <div class="flex items-center justify-between mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                            <h3 class="font-bold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                <svg class="w-4 h-4 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                {{ $module }}
                            </h3>
                        </div>
                        <div class="space-y-3">
                            @foreach($perms as $perm)
                                <label class="flex items-start gap-3 group cursor-pointer">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" 
                                            {{ $user->roleModel->permissions->contains($perm->id) ? 'checked' : '' }}
                                            class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 transition">
                                    </div>
                                    <div class="text-sm">
                                        <span class="font-medium text-gray-700 dark:text-gray-300 group-hover:text-brand-600 transition-colors">
                                            {{ $perm->name }}
                                        </span>
                                        <p class="text-xs text-gray-400 dark:text-gray-500">Allow access to {{ strtolower($perm->name) }}</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/10 flex justify-end gap-3">
            <a href="{{ route('staff.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 transition">Cancel</a>
            <button type="submit" class="px-6 py-2 bg-brand-500 text-white rounded-lg font-bold hover:bg-brand-600 shadow-sm transition">Save Permissions</button>
        </div>
    </form>
</div>
@endsection
