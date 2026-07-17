@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb pageTitle="Location Setting" />

{{-- ══════════════════════════════════════════════════════
     MAIN CARD
     ══════════════════════════════════════════════════════ --}}
<div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] overflow-hidden"
     x-data="{
        showAddModal: false,
        showEditModal: false,
        showDeleteModal: false,
        editLocation: {},
        deleteLocation: {},
        openEdit(loc) {
            this.editLocation = { ...loc };
            this.showEditModal = true;
        },
        openDelete(loc) {
            this.deleteLocation = { ...loc };
            this.showDeleteModal = true;
        }
     }">

    {{-- Header Bar --}}
    <div class="flex flex-col gap-4 px-6 py-5 border-b border-gray-200 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Location Setting</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Manage office locations, states, and regional context (Super Admin only)</p>
        </div>
        <button @click="showAddModal = true"
            class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            Add New Location
        </button>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('locations.index') }}" class="flex flex-col gap-3 px-6 py-4 border-b border-gray-100 dark:border-gray-800 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, state, or address..."
                class="w-full rounded-lg border border-gray-200 bg-gray-50 pl-9 pr-4 py-2.5 text-sm text-gray-700 focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500"/>
        </div>
        @if(request('search'))
            <a href="{{ route('locations.index') }}" class="text-sm text-brand-500 hover:text-brand-600 font-medium whitespace-nowrap">Clear filters</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="bg-gray-50/80 dark:bg-white/[0.02] border-b border-gray-200 dark:border-gray-800">
                    <th class="px-6 py-3.5 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wider">#</th>
                    <th class="px-6 py-3.5 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wider">Location Name</th>
                    <th class="px-6 py-3.5 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wider">State</th>
                    <th class="px-6 py-3.5 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wider hidden lg:table-cell">Address</th>
                    <th class="px-6 py-3.5 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wider">Statistics</th>
                    <th class="px-6 py-3.5 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3.5 font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($locations as $loc)
                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors">
                    <td class="px-6 py-4 text-gray-400 text-xs">{{ $loop->iteration + ($locations->currentPage() - 1) * $locations->perPage() }}</td>
                    <td class="px-6 py-4">
                        <div class="font-semibold text-gray-800 dark:text-white text-sm">{{ $loc->name }}</div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 font-bold">
                        {{ $loc->state }}
                    </td>
                    <td class="px-6 py-4 hidden lg:table-cell">
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $loc->address }}</span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400" title="Users">U: {{ $loc->users_count }}</span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400" title="Clients">C: {{ $loc->clients_count }}</span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-purple-50 text-purple-600 dark:bg-purple-900/20 dark:text-purple-400" title="Employees">E: {{ $loc->employees_count }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        @if($loc->is_active)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                Active
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                Inactive
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button type="button"
                                @click="openEdit({
                                    id: {{ $loc->id }},
                                    name: '{{ addslashes($loc->name) }}',
                                    state: '{{ addslashes($loc->state) }}',
                                    address: '{{ addslashes($loc->address) }}',
                                    is_active: {{ $loc->is_active ? 1 : 0 }}
                                })"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:text-blue-400 dark:hover:bg-blue-900/30 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Edit
                            </button>
                            <button type="button"
                                @click="openDelete({ id: {{ $loc->id }}, name: '{{ addslashes($loc->name) }}' })"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                Delete
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-16 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <div class="text-sm text-gray-500 dark:text-gray-400">No locations found matching your search.</div>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($locations->hasPages())
    <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-800">
        {{ $locations->links() }}
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════
         MODALS (Teleported to Body for consistent centering)
         ══════════════════════════════════════════════════════ --}}
    <template x-teleport="body">
        <div x-show="showAddModal || showEditModal || showDeleteModal" x-cloak>
            
            {{-- ADD LOCATION MODAL --}}
            <div x-show="showAddModal" 
                x-transition:enter="transition ease-out duration-300" 
                x-transition:enter-start="opacity-0 scale-95" 
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-200" 
                x-transition:leave-start="opacity-100 scale-100" 
                x-transition:leave-end="opacity-0 scale-95"
                class="fixed inset-0 z-[999999] flex items-center justify-center p-4">
                
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="showAddModal = false"></div>
                
                <div class="relative w-full max-w-lg rounded-[2rem] bg-white dark:bg-gray-900 shadow-2xl overflow-hidden transform transition-all" @click.stop>
                    <div class="px-8 py-6 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-white/[0.02]">
                        <h3 class="w-full text-center text-xl font-bold text-gray-800 dark:text-white uppercase tracking-widest">Add New Location</h3>
                    </div>
                    <form method="POST" action="{{ route('locations.store') }}" class="p-8 space-y-6">
                        @csrf
                        <div class="grid grid-cols-2 gap-6">
                            <div class="col-span-2">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Location Name</label>
                                <input type="text" name="name" required placeholder="e.g., Michigan Office"
                                    class="w-full rounded-xl border border-gray-100 dark:border-white/[0.05] dark:bg-white/[0.03] dark:text-white px-4 py-3 text-sm focus:ring-4 focus:ring-brand-500/10 outline-none transition-all"/>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">State</label>
                                <input type="text" name="state" required placeholder="e.g., MI"
                                    class="w-full rounded-xl border border-gray-100 dark:border-white/[0.05] dark:bg-white/[0.03] dark:text-white px-4 py-3 text-sm focus:ring-4 focus:ring-brand-500/10 outline-none transition-all"/>
                            </div>
                            <div class="flex items-center pt-5">
                                <label class="flex items-center gap-3 cursor-pointer group">
                                    <input type="checkbox" name="is_active" value="1" checked class="peer w-5 h-5 rounded-lg border-gray-200 dark:border-gray-700 text-brand-500 focus:ring-brand-500/20 transition-all">
                                    <span class="text-[10px] font-black text-gray-400 group-hover:text-gray-700 dark:group-hover:text-white transition-colors uppercase tracking-widest">Mark Active</span>
                                </label>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Full Address</label>
                                <textarea name="address" required rows="2" placeholder="Full office address..."
                                    class="w-full rounded-xl border border-gray-100 dark:border-white/[0.05] dark:bg-white/[0.03] dark:text-white px-4 py-3 text-sm focus:ring-4 focus:ring-brand-500/10 outline-none transition-all"></textarea>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 pt-4 border-t border-gray-50 dark:border-gray-800">
                            <button type="button" @click="showAddModal = false"
                                class="flex-1 px-4 py-4 text-[11px] font-black text-gray-400 bg-gray-50 dark:bg-white/[0.02] rounded-xl hover:bg-gray-100 dark:hover:bg-white/[0.05] uppercase tracking-widest transition-all">
                                Cancel
                            </button>
                            <button type="submit"
                                class="flex-1 px-4 py-4 text-[11px] font-black text-white bg-brand-500 rounded-xl hover:bg-brand-600 uppercase tracking-widest shadow-lg shadow-brand-500/20 transition-all transform active:scale-95">
                                Create Location
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- EDIT LOCATION MODAL --}}
            <div x-show="showEditModal" 
                x-transition:enter="transition ease-out duration-300" 
                x-transition:enter-start="opacity-0 scale-95" 
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-200" 
                x-transition:leave-start="opacity-100 scale-100" 
                x-transition:leave-end="opacity-0 scale-95"
                class="fixed inset-0 z-[999999] flex items-center justify-center p-4">
                
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="showEditModal = false"></div>
                
                <div class="relative w-full max-w-lg rounded-[2rem] bg-white dark:bg-gray-900 shadow-2xl overflow-hidden transform transition-all" @click.stop>
                    <div class="px-8 py-6 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-white/[0.02]">
                        <h3 class="w-full text-center text-xl font-bold text-gray-800 dark:text-white uppercase tracking-widest">Update Location</h3>
                    </div>
                    <form method="POST" :action="`/locations/${editLocation.id}`" class="p-8 space-y-6">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-2 gap-6">
                            <div class="col-span-2">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Location Name</label>
                                <input type="text" name="name" :value="editLocation.name" required
                                    class="w-full rounded-xl border border-gray-100 dark:border-white/[0.05] dark:bg-white/[0.03] dark:text-white px-4 py-3 text-sm focus:ring-4 focus:ring-brand-500/10 outline-none transition-all"/>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">State</label>
                                <input type="text" name="state" :value="editLocation.state" required
                                    class="w-full rounded-xl border border-gray-100 dark:border-white/[0.05] dark:bg-white/[0.03] dark:text-white px-4 py-3 text-sm focus:ring-4 focus:ring-brand-500/10 outline-none transition-all"/>
                            </div>
                            <div class="flex items-center pt-5">
                                <label class="flex items-center gap-3 cursor-pointer group">
                                    <input type="checkbox" name="is_active" value="1" :checked="editLocation.is_active" class="peer w-5 h-5 rounded-lg border-gray-200 dark:border-gray-700 text-brand-500 focus:ring-brand-500/20 transition-all">
                                    <span class="text-[10px] font-black text-gray-400 group-hover:text-gray-700 dark:group-hover:text-white transition-colors uppercase tracking-widest">Active Status</span>
                                </label>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Full Address</label>
                                <textarea name="address" required rows="2" x-text="editLocation.address"
                                    class="w-full rounded-xl border border-gray-100 dark:border-white/[0.05] dark:bg-white/[0.03] dark:text-white px-4 py-3 text-sm focus:ring-4 focus:ring-brand-500/10 outline-none transition-all"></textarea>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 pt-4 border-t border-gray-50 dark:border-gray-800">
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
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-[999999] flex items-center justify-center p-4">
                
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="showDeleteModal = false"></div>
                
                <div @click.stop class="relative w-full max-w-sm rounded-[2.5rem] bg-white dark:bg-gray-900 shadow-2xl p-10 text-center">
                    <div class="flex items-center justify-center w-16 h-16 rounded-full bg-red-50 dark:bg-red-900/10 mx-auto mb-6">
                        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white uppercase tracking-widest mb-2">Delete Location?</h3>
                    <p class="text-[11px] font-semibold text-gray-400 dark:text-gray-500 mb-8 leading-relaxed px-4">
                        Are you sure you want to delete <span class="font-black text-gray-800 dark:text-white" x-text="deleteLocation.name"></span>? This action is permanent.
                    </p>
                    <div class="flex items-center gap-3">
                        <button type="button" @click="showDeleteModal = false"
                            class="flex-1 px-4 py-4 text-[11px] font-black text-gray-400 bg-gray-50 dark:bg-white/[0.02] rounded-xl hover:bg-gray-100 dark:hover:bg-white/[0.05] uppercase tracking-widest transition-all">
                            No, Keep
                        </button>
                        <form method="POST" :action="`/locations/${deleteLocation.id}`" class="flex-1">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="w-full px-4 py-4 text-[11px] font-black text-white bg-red-500 rounded-xl hover:bg-red-600 uppercase tracking-widest shadow-lg shadow-red-500/20 transition-all">
                                Yes, Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </template>

    </div>

</div><!-- End Main Card -->
@endsection
