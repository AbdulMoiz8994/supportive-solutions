@extends('layouts.app')

@section('content')
<div class="space-y-6 pb-20 w-full px-2" x-data="{
    showEditModal: false,
    editUser: {},
    search: '',
    currentPage: 1,
    perPage: 10,
    allData: @js($staff),
    
    get filteredData() {
        const s = this.search.toLowerCase();
        return this.allData.filter(u => 
            u.name.toLowerCase().includes(s) || 
            u.email.toLowerCase().includes(s)
        );
    },

    get paginatedData() {
        const start = (this.currentPage - 1) * this.perPage;
        return this.filteredData.slice(start, start + this.perPage);
    },

    get totalEntries() { return this.filteredData.length; },
    get startEntry() { return this.totalEntries === 0 ? 0 : (this.currentPage - 1) * this.perPage + 1; },
    get endEntry() { 
        const end = this.currentPage * this.perPage;
        return end > this.totalEntries ? this.totalEntries : end;
    },
    get totalPages() { return Math.ceil(this.totalEntries / this.perPage) || 1; },
    
    nextPage() { if (this.currentPage < this.totalPages) this.currentPage++; },
    prevPage() { if (this.currentPage > 1) this.currentPage--; },

    openEdit(user) {
        this.editUser = { ...user };
        this.showEditModal = true;
    }
}">
    
    @if(session('debug_setup_url'))
    <div class="mb-6 p-4 rounded-2xl bg-blue-50 border border-blue-100 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600 shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-[13px] font-bold text-[#1e293b]">Debug Mode: Staff Onboarding Link</p>
                <p class="text-[11px] text-blue-600 font-medium truncate max-w-md">{{ session('debug_setup_url') }}</p>
            </div>
        </div>
        <button
            @click="navigator.clipboard.writeText(@js(session('debug_setup_url'))).then(() => $store.dialog.alert({ title: 'Link copied', message: 'The staff onboarding link is on your clipboard.', variant: 'success', confirmLabel: 'Done' }))"
            class="px-5 py-2 bg-white border border-blue-200 rounded-lg text-[12px] font-bold text-blue-600 hover:bg-blue-50 transition-all shadow-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
            Copy Link
        </button>
    </div>
    @endif

    <!-- Page Header -->
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 pt-4 px-2">
        <div class="space-y-1.5">
            <h1 class="text-[32px] font-bold text-[#1e293b] tracking-tight leading-tight">My Staff</h1>
            <p class="text-[12px] font-medium text-[#64748b]">Manage access across all organizations • HIPAA access control</p>
        </div>
        
        <div class="flex flex-wrap items-center gap-3">
            <button class="flex items-center gap-2 px-6 py-2.5 bg-white border border-[#cbd5e1] rounded-xl text-[#1e293b] hover:bg-gray-50 transition-all shadow-sm">
                <svg class="w-4 h-4 text-[#64748b]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                <span class="text-[12px] font-bold tracking-wide">Export</span>
            </button>

            <a href="{{ route('staff.create') }}" class="bg-[#2563eb] text-white px-6 py-2.5 rounded-xl text-[12px] font-bold tracking-wide shadow-lg shadow-[#2563eb]/20 hover:bg-[#1d4ed8] transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m-7-7h14"></path></svg>
                Add Staff
            </a>
        </div>
    </div>

    <!-- Main Staff Card -->
    <div class="bg-[#eff6ff] rounded-[24px] border border-blue-100/50 overflow-hidden shadow-sm">
        <!-- Card Toolbar -->
        <div class="px-8 py-5 flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-blue-100/20 bg-blue-50/10">
            <h2 class="text-[16px] font-bold text-[#1e293b]">All Staff</h2>
            
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative min-w-[240px]">
                    <svg class="w-4 h-4 text-[#94a3b8] absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" x-model="search" @input="currentPage = 1" placeholder="Search Users" 
                        class="w-full pl-10 pr-4 py-2 bg-white border border-[#e2e8f0] rounded-[10px] text-[12px] font-bold text-[#1e293b] placeholder:text-[#94a3b8] focus:ring-2 focus:ring-blue-500/10 outline-none transition-all">
                </div>

                <button class="bg-white border border-[#e2e8f0] px-4 py-2.5 rounded-[12px] text-[11px] font-bold text-[#475569] shadow-sm flex items-center gap-3 hover:bg-gray-50 transition-colors">
                    All Roles
                    <svg class="w-3.5 h-3.5 text-[#94a3b8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                </button>

                <button class="bg-white border border-[#e2e8f0] px-4 py-2.5 rounded-[12px] text-[11px] font-bold text-[#475569] shadow-sm flex items-center gap-3 hover:bg-gray-50 transition-colors">
                    All Organization
                    <svg class="w-3.5 h-3.5 text-[#94a3b8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                </button>
            </div>
        </div>

        <!-- Staff Table -->
        <div class="w-full overflow-x-auto no-scrollbar">
            <table class="w-full min-w-[1000px] border-collapse">
                <thead>
                    <tr class="bg-white border-b border-blue-100/20">
                        <th class="px-8 py-4 text-left text-[11px] font-black text-[#94a3b8] uppercase tracking-wider">User</th>
                        <th class="px-6 py-4 text-left text-[11px] font-black text-[#94a3b8] uppercase tracking-wider">Role</th>
                        <th class="px-6 py-4 text-left text-[11px] font-black text-[#94a3b8] uppercase tracking-wider">Organization</th>
                        <th class="px-6 py-4 text-left text-[11px] font-black text-[#94a3b8] uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-left text-[11px] font-black text-[#94a3b8] uppercase tracking-wider">Last Login</th>
                        <th class="px-8 py-4 text-right text-[11px] font-black text-[#94a3b8] uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-blue-100/10 bg-transparent">
                    <template x-for="u in paginatedData" :key="u.id">
                        <tr class="hover:bg-blue-50/30 transition-colors">
                            <td class="px-8 py-4 first:rounded-l-2xl">
                                <div class="flex items-center gap-3">
                                    <div class="w-[32px] h-[32px] rounded-lg overflow-hidden shrink-0 border border-[#e2e8f0] shadow-sm">
                                        <img :src="'https://ui-avatars.com/api/?name=' + encodeURIComponent(u.name) + '&background=f1f5f9&color=1e293b&font-size=0.4&bold=true'" class="w-full h-full object-cover">
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-[13px] font-bold text-[#1e293b]" x-text="u.name"></span>
                                        <span class="text-[11px] font-medium text-[#94a3b8]" x-text="u.email"></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="bg-pink-50 text-pink-600 text-[10px] font-black px-2.5 py-1 rounded-md uppercase tracking-wider" x-text="u.role"></span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <span class="text-[12px] font-bold text-[#1e293b]" x-text="u.locations?.[0]?.name || 'No Organization'"></span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-[12px] font-bold" :class="u.is_active ? 'text-blue-500' : 'text-pink-500'" x-text="u.is_active ? 'Active' : 'Suspended'"></span>
                            </td>
                            <td class="px-6 py-4 text-[12px] font-bold text-[#1e293b]">
                                Now
                            </td>
                            <td class="px-8 py-4 text-right last:rounded-r-2xl">
                                <a :href="'/staff/' + u.id" class="inline-flex items-center justify-center px-5 py-2 bg-blue-50 text-blue-600 text-[11px] font-black rounded-xl hover:bg-blue-600 hover:text-white transition-all duration-300 shadow-sm hover:shadow-blue-200 uppercase tracking-wider">
                                    View Detail
                                </a>
                            </td>
>
                        </tr>
                    </template>
                    <tr x-show="paginatedData.length === 0">
                        <td colspan="6" class="px-8 py-10 text-center text-[#94a3b8] font-bold italic">No staff found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination Section -->
    <div class="px-2 py-6">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-[13px] font-bold text-[#94a3b8]">
                Showing <span x-text="startEntry" class="text-[#1e293b]"></span> to <span x-text="endEntry" class="text-[#1e293b]"></span> of <span x-text="totalEntries" class="text-[#1e293b]"></span> users
            </p>
            <div class="flex items-center gap-2">
                <button @click="prevPage" :disabled="currentPage === 1" class="px-4 py-2 flex items-center justify-center rounded-xl border border-[#e2e8f0] bg-white text-[#475569] text-[12px] font-bold shadow-sm hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all">
                    Previous
                </button>
                <button @click="nextPage" :disabled="currentPage === totalPages" class="px-4 py-2 flex items-center justify-center rounded-xl border border-[#e2e8f0] bg-white text-[#475569] text-[12px] font-bold shadow-sm hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all">
                    Next
                </button>
            </div>
        </div>
    </div>

    <!-- Modals (Teleported) -->
    <template x-teleport="body">
        <div x-show="showEditModal" x-cloak>
            <div class="fixed inset-0 z-[999999] flex items-center justify-center p-4"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showEditModal = false"></div>
                
                <div class="relative w-full max-w-2xl bg-white rounded-[24px] shadow-2xl overflow-hidden max-h-[90vh] flex flex-col" @click.stop>
                    <!-- Modal Header -->
                    <div class="px-8 py-7 border-b border-gray-50 flex justify-between items-start bg-white">
                        <div class="space-y-1">
                            <h3 class="text-[20px] font-bold text-[#1e293b]">Update Staff Profile</h3>
                            <p class="text-[13px] text-[#64748b]">Modify permissions and details for this professional</p>
                        </div>
                        <button @click="showEditModal = false" class="w-8 h-8 rounded-full border border-gray-100 flex items-center justify-center text-gray-400 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="overflow-y-auto custom-scrollbar">
                        <form :action="`/staff/${editUser.id}`" method="POST" class="p-8 space-y-6" autocomplete="off">
                            @csrf
                            @method('PUT')
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="md:col-span-2">
                                    <label class="block text-[13px] font-bold text-[#1e293b] mb-2">Full Name</label>
                                    <input type="text" name="name" x-model="editUser.name" required 
                                        class="w-full px-4 py-3 rounded-[10px] border border-[#e2e8f0] text-[13px] font-medium focus:border-blue-200 focus:ring-2 focus:ring-blue-50 outline-none transition-all">
                                </div>

                                <div class="col-span-1">
                                    <label class="block text-[13px] font-bold text-[#1e293b] mb-2">Email Address</label>
                                    <input type="email" name="email" x-model="editUser.email" required 
                                        class="w-full px-4 py-3 rounded-[10px] border border-[#e2e8f0] text-[13px] font-medium focus:border-blue-200 focus:ring-2 focus:ring-blue-50 outline-none transition-all">
                                </div>

                                <div class="col-span-1">
                                    <label class="block text-[13px] font-bold text-[#1e293b] mb-2">Phone Number</label>
                                    <input type="text" name="phone" x-model="editUser.phone" 
                                        class="w-full px-4 py-3 rounded-[10px] border border-[#e2e8f0] text-[13px] font-medium focus:border-blue-200 focus:ring-2 focus:ring-blue-50 outline-none transition-all">
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-[13px] font-bold text-[#1e293b] mb-2">Primary Role</label>
                                    <select name="role" x-model="editUser.role" required 
                                        class="w-full px-4 py-3 rounded-[10px] border border-[#e2e8f0] text-[13px] font-medium focus:border-blue-200 focus:ring-2 focus:ring-blue-50 outline-none transition-all appearance-none" style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2394a3b8%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1em;">
                                        @foreach($roles as $role)
                                            <option value="{{ $role->name }}">{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-[13px] font-bold text-[#1e293b] mb-3 pl-1">Assign Locations</label>
                                    <div class="grid grid-cols-2 gap-3 p-5 rounded-2xl border border-blue-50 bg-blue-50/20">
                                        @foreach($locations as $loc)
                                            <label class="flex items-center gap-3 cursor-pointer group">
                                                <input type="checkbox" name="location_ids[]" value="{{ $loc->id }}" 
                                                    :checked="editUser.location_ids?.includes({{ $loc->id }})"
                                                    class="peer w-5 h-5 rounded-lg border-gray-200 text-blue-500 focus:ring-blue-500/20 transition-all">
                                                <span class="text-[12px] font-bold text-[#64748b] group-hover:text-blue-600 transition-colors tracking-tight">{{ $loc->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Footer -->
                            <div class="flex items-center justify-end gap-3 pt-4 pb-2">
                                <button type="button" @click="showEditModal = false" 
                                    class="px-6 py-2.5 rounded-[10px] border border-gray-100 bg-white text-[13px] font-bold text-[#64748b] hover:bg-gray-50 flex items-center gap-2 transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    Close
                                </button>
                                <button type="submit" 
                                    class="px-8 py-2.5 rounded-[10px] bg-[#2563eb] text-white text-[13px] font-bold hover:bg-blue-700 flex items-center gap-2 transition-all shadow-lg shadow-blue-500/20">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                                    Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
