@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb pageTitle="User Management" />

{{-- ══════════════════════════════════════════════════════
     MAIN CARD
══════════════════════════════════════════════════════ --}}
<div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] overflow-hidden"
     x-data="{
        showAddModal: false,
        showEditModal: false,
        showDeleteModal: false,
        editUser: {},
        deleteUser: {},
        openEdit(user) {
            this.editUser = { ...user };
            this.showEditModal = true;
        },
        openDelete(user) {
            this.deleteUser = { ...user };
            this.showDeleteModal = true;
        }
     }">

    {{-- Header Bar --}}
    <div class="flex flex-col gap-4 px-6 py-5 border-b border-gray-200 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">System Users</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Manage all user accounts and role assignments (Super Admin only)</p>
        </div>
        <button @click="showAddModal = true"
            class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            Add New User
        </button>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('users.index') }}" class="flex flex-col gap-3 px-6 py-4 border-b border-gray-100 dark:border-gray-800 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or email..."
                class="w-full rounded-lg border border-gray-200 bg-gray-50 pl-9 pr-4 py-2.5 text-sm text-gray-700 focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500"/>
        </div>
        <select name="role" onchange="this.form.submit()"
            class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-700 focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
            <option value="">All Roles</option>
            @foreach($roles as $role)
                <option value="{{ $role }}" @selected(request('role') === $role)>{{ $role }}</option>
            @endforeach
        </select>
        @if(request('search') || request('role'))
            <a href="{{ route('users.index') }}" class="text-sm text-brand-500 hover:text-brand-600 font-medium whitespace-nowrap">Clear filters</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="bg-gray-50/80 dark:bg-white/[0.02] border-b border-gray-200 dark:border-gray-800">
                    <th class="px-6 py-3.5 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wider">#</th>
                    <th class="px-6 py-3.5 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wider">User</th>
                    <th class="px-6 py-3.5 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wider">Role</th>
                    <th class="px-6 py-3.5 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wider hidden md:table-cell">Organization</th>
                    <th class="px-6 py-3.5 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wider hidden lg:table-cell">Joined</th>
                    <th class="px-6 py-3.5 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($users as $u)
                @php
                    $initials = collect(explode(' ', $u->name))->map(fn($w) => strtoupper($w[0]))->take(2)->join('');
                    $roleColors = [
                        'Super Administrator' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
                        'Administrator'       => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                        'Operations Staff'    => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                        'Employee'            => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
                    ];
                    $avatarColors = [
                        'Super Administrator' => 'bg-purple-500',
                        'Administrator'       => 'bg-blue-500',
                        'Operations Staff'    => 'bg-green-500',
                        'Employee'            => 'bg-orange-500',
                    ];
                    $roleColor   = $roleColors[$u->role] ?? 'bg-gray-100 text-gray-700';
                    $avatarColor = $avatarColors[$u->role] ?? 'bg-gray-400';
                @endphp
                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors {{ $u->id === auth()->id() ? 'bg-brand-50/30 dark:bg-brand-900/5' : '' }}">
                    {{-- Number --}}
                    <td class="px-6 py-4 text-gray-400 text-xs">{{ $loop->iteration + ($users->currentPage() - 1) * $users->perPage() }}</td>

                    {{-- User Info --}}
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0 flex items-center justify-center w-9 h-9 rounded-full {{ $avatarColor }} text-white font-semibold text-xs shadow-sm">
                                {{ $initials }}
                            </div>
                            <div>
                                <div class="font-semibold text-gray-800 dark:text-white text-sm flex items-center gap-1.5">
                                    {{ $u->name }}
                                    @if($u->id === auth()->id())
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-brand-100 text-brand-600 dark:bg-brand-900/30 dark:text-brand-400">You</span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-400 dark:text-gray-500">{{ $u->email }}</div>
                            </div>
                        </div>
                    </td>

                    {{-- Role --}}
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $roleColor }}">
                            {{ $u->role }}
                        </span>
                    </td>

                    {{-- Organization --}}
                    <td class="px-6 py-4 hidden md:table-cell">
                        @if($u->organization)
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ $u->organization->name }}</span>
                        @else
                            <span class="text-xs text-gray-400 italic">— System Level —</span>
                        @endif
                    </td>

                    {{-- Joined --}}
                    <td class="px-6 py-4 hidden lg:table-cell">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $u->created_at->format('M d, Y') }}</span>
                    </td>

                    {{-- Actions --}}
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            {{-- Edit --}}
                            <button type="button"
                                @click="openEdit({
                                    id: {{ $u->id }},
                                    name: '{{ addslashes($u->name) }}',
                                    email: '{{ addslashes($u->email) }}',
                                    role: '{{ $u->role }}',
                                    organization_id: '{{ $u->organization_id }}'
                                })"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:text-blue-400 dark:hover:bg-blue-900/30 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Edit
                            </button>

                            {{-- Delete (cannot delete self) --}}
                            @if($u->id !== auth()->id())
                            <button type="button"
                                @click="openDelete({ id: {{ $u->id }}, name: '{{ addslashes($u->name) }}' })"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                Delete
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-16 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <div class="flex items-center justify-center w-14 h-14 rounded-full bg-gray-100 dark:bg-gray-800">
                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">No users found matching your filters.</div>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($users->hasPages())
    <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-800">
        {{ $users->links() }}
    </div>
    @endif

    {{-- Summary --}}
    <div class="px-6 py-3 bg-gray-50/80 dark:bg-white/[0.01] border-t border-gray-100 dark:border-gray-800 text-xs text-gray-400">
        Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }} users
    </div>



    {{-- ══════════════════════════════════════════════════════
         MODALS (Teleported to Body for consistent centering)
         ══════════════════════════════════════════════════════ --}}
    <template x-teleport="body">
        <div x-show="showAddModal || showEditModal || showDeleteModal" x-cloak>
            
            {{-- ADD USER MODAL --}}
            <div x-show="showAddModal" 
                 class="fixed inset-0 z-[999999] flex items-center justify-center p-4"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">
                
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="showAddModal = false"></div>
                
                <div class="relative w-full max-w-xl bg-white dark:bg-gray-900 rounded-[2rem] shadow-2xl overflow-hidden transform transition-all" @click.stop>
                    <div class="px-8 py-6 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-white/[0.02]">
                        <h3 class="w-full text-center text-xl font-bold text-gray-800 dark:text-white uppercase tracking-widest">Create New System User</h3>
                    </div>
                    <form method="POST" action="{{ route('users.store') }}" class="p-8 space-y-6">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="col-span-2">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Full Name</label>
                                <input type="text" name="name" required placeholder="e.g. John Smith"
                                    class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all"/>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Email Address</label>
                                <input type="email" name="email" required placeholder="user@agency.com"
                                    class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all"/>
                            </div>
                            <div class="col-span-1">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Password</label>
                                <input type="password" name="password" required placeholder="Min. 8 chars"
                                    class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all"/>
                            </div>
                            <div class="col-span-1">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Confirm Password</label>
                                <input type="password" name="password_confirmation" required placeholder="Repeat password"
                                    class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all"/>
                            </div>
                            <div class="col-span-1">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Assign Role</label>
                                <select name="role" required class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                    <option value="">Select a role</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role }}">{{ $role }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-1">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Organization</label>
                                <select name="organization_id" class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                    <option value="">— System Level —</option>
                                    @foreach($organizations as $org)
                                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 pt-6 mt-2 border-t border-gray-50 dark:border-gray-800">
                            <button type="button" @click="showAddModal = false"
                                class="flex-1 px-4 py-4 text-[11px] font-black text-gray-400 bg-gray-50 dark:bg-white/[0.02] rounded-xl hover:bg-gray-100 dark:hover:bg-white/[0.05] uppercase tracking-widest transition-all">
                                Cancel
                            </button>
                            <button type="submit"
                                class="flex-1 px-4 py-4 text-[11px] font-black text-white bg-brand-500 rounded-xl hover:bg-brand-600 uppercase tracking-widest shadow-lg shadow-brand-500/20 transition-all transform active:scale-95">
                                Create Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- EDIT USER MODAL --}}
            <div x-show="showEditModal" 
                 class="fixed inset-0 z-[999999] flex items-center justify-center p-4"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">
                
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="showEditModal = false"></div>
                
                <div class="relative w-full max-w-xl bg-white dark:bg-gray-900 rounded-[2rem] shadow-2xl overflow-hidden transform transition-all" @click.stop>
                    <div class="px-8 py-6 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-white/[0.02]">
                        <h3 class="w-full text-center text-xl font-bold text-gray-800 dark:text-white uppercase tracking-widest">Update User Profile</h3>
                    </div>
                    <form method="POST" :action="`/users/${editUser.id}`" class="p-8 space-y-6">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="col-span-2">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Full Name</label>
                                <input type="text" name="name" x-model="editUser.name" required
                                    class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all"/>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Email Address</label>
                                <input type="email" name="email" x-model="editUser.email" required 
                                    class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all"/>
                            </div>
                            <div class="col-span-1">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">New Password (Optional)</label>
                                <input type="password" name="password" placeholder="Leave blank to keep current"
                                    class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all"/>
                            </div>
                            <div class="col-span-1">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Confirm New Password</label>
                                <input type="password" name="password_confirmation" placeholder="Repeat if changing"
                                    class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all"/>
                            </div>
                            <div class="col-span-1">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Assign Role</label>
                                <select name="role" required class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                    @foreach($roles as $role)
                                        <option :value="'{{ $role }}'" :selected="editUser.role === '{{ $role }}'">{{ $role }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-1">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Organization</label>
                                <select name="organization_id" class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                    <option value="">— None (System Level) —</option>
                                    @foreach($organizations as $org)
                                        <option value="{{ $org->id }}" :selected="editUser.organization_id == {{ $org->id }}">{{ $org->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 pt-6 mt-2 border-t border-gray-50 dark:border-gray-800">
                            <button type="button" @click="showEditModal = false"
                                class="flex-1 px-4 py-4 text-[11px] font-black text-gray-400 bg-gray-50 dark:bg-white/[0.02] rounded-xl hover:bg-gray-100 dark:hover:bg-white/[0.05] uppercase tracking-widest transition-all">
                                Cancel
                            </button>
                            <button type="submit"
                                class="flex-1 px-4 py-4 text-[11px] font-black text-white bg-brand-500 rounded-xl hover:bg-brand-600 uppercase tracking-widest shadow-lg shadow-brand-500/20 transition-all transform active:scale-95">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- DELETE CONFIRM MODAL --}}
            <div x-show="showDeleteModal" 
                 class="fixed inset-0 z-[999999] flex items-center justify-center p-4"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">
                
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="showDeleteModal = false"></div>
                
                <div class="relative w-full max-w-sm bg-white dark:bg-gray-900 rounded-[2rem] shadow-2xl overflow-hidden transform transition-all p-8" @click.stop>
                    <div class="text-center">
                        <div class="flex items-center justify-center w-20 h-20 rounded-full bg-red-50 dark:bg-red-500/10 mx-auto mb-6">
                            <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800 dark:text-white uppercase tracking-widest mb-2">Delete User?</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-8 leading-relaxed">
                            Are you sure you want to delete <span class="font-bold text-gray-800 dark:text-white" x-text="deleteUser.name"></span>? This action is permanent and cannot be reversed.
                        </p>
                        <div class="flex items-center gap-4">
                            <button type="button" @click="showDeleteModal = false"
                                class="flex-1 px-4 py-4 text-[11px] font-black text-gray-400 bg-gray-50 dark:bg-white/[0.02] rounded-xl hover:bg-gray-100 dark:hover:bg-white/[0.05] uppercase tracking-widest transition-all">
                                Cancel
                            </button>
                            <form method="POST" :action="`/users/${deleteUser.id}`" class="flex-1">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="w-full px-4 py-4 text-[11px] font-black text-white bg-red-500 rounded-xl hover:bg-red-600 uppercase tracking-widest shadow-lg shadow-red-500/20 transition-all transform active:scale-95 text-center">
                                    Yes, Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

</div><!-- End Main Card -->
@endsection
