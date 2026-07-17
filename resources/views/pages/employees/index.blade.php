@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Employee Registry" />

    <div x-data="{ 
        openModal: false,
        editModal: false,
        search: '',
        currentPage: 1,
        perPage: 10,
        allData: @js($employees),
        currentEmployee: { id: null, first_name: '', last_name: '', email: '', phone: '', address: '', position: '', hire_date: '', champs_username: '', champs_association_date: '', status_id: '' },

        get filteredData() {
            const searchLower = this.search.toLowerCase();
            return this.allData
                .filter(e => 
                    (e.first_name + ' ' + (e.last_name || '')).toLowerCase().includes(searchLower) ||
                    (e.position && e.position.toLowerCase().includes(searchLower)) ||
                    (e.email && e.email.toLowerCase().includes(searchLower))
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

        editEmployee(employee) {
            this.currentEmployee = { ...employee };
            this.editModal = true;
            this.$nextTick(() => {
                // Sync Hire Date Picker
                const hirePicker = document.getElementById('edit-hire-picker');
                if (hirePicker && hirePicker._flatpickr) {
                    hirePicker._flatpickr.setDate(employee.hire_date);
                }
                // Sync CHAMPS Date Picker
                const champsPicker = document.getElementById('edit-champs-picker');
                if (champsPicker && champsPicker._flatpickr) {
                    champsPicker._flatpickr.setDate(employee.champs_association_date);
                }
            });
        }
    }" x-init="$watch('search', () => currentPage = 1)">
        
        <!-- Table Card -->
        <div class="overflow-hidden rounded-xl bg-white dark:bg-white/[0.03] shadow-theme-xs border border-gray-100 dark:border-white/[0.05]">
            <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between border-b border-gray-100 dark:border-white/[0.05]">
                <div class="flex items-center gap-4">
                    <button @click="openModal = true" class="px-4 py-2 text-sm font-bold text-white transition rounded-lg bg-brand-500 hover:bg-brand-600">
                        Add New Employee
                    </button>
                    <div class="relative">
                        <input x-model="search" type="text" placeholder="Search team..." class="px-4 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand-500/20 outline-none w-64 dark:bg-dark-900 dark:border-white/5">
                    </div>
                </div>
            </div>

            <div class="max-w-full overflow-x-auto">
                <table class="min-w-full text-left">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-white/[0.05]">
                            <th class="px-6 py-4 font-semibold text-gray-700 dark:text-white/90 text-[10px] uppercase tracking-widest">Name</th>
                            <th class="px-6 py-4 font-semibold text-gray-700 dark:text-white/90 text-[10px] uppercase tracking-widest">Position</th>
                            <th class="px-6 py-4 font-semibold text-gray-700 dark:text-white/90 text-[10px] uppercase tracking-widest">Contact</th>
                            <th class="px-6 py-4 font-semibold text-gray-700 dark:text-white/90 text-[10px] uppercase tracking-widest">Status</th>
                            <th class="px-6 py-4 font-semibold text-gray-700 dark:text-white/90 text-[10px] uppercase tracking-widest text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.05]">
                        <template x-for="employee in paginatedData" :key="employee.id">
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.01]">
                                <td class="px-6 py-4 text-sm font-bold text-gray-800 dark:text-white/90" x-text="employee.first_name + ' ' + (employee.last_name || '')"></td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 text-[10px] font-bold rounded-lg bg-gray-50 text-gray-600 border border-gray-100" x-text="employee.position"></span>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-xs text-gray-500" x-text="employee.email"></p>
                                    <p class="text-[10px] text-gray-400 font-bold" x-text="employee.phone || '--'"></p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 text-[10px] font-black rounded-full uppercase tracking-widest"
                                          :class="{
                                              'bg-green-100 text-green-700': employee.status === 'Active',
                                              'bg-yellow-100 text-yellow-700': employee.status === 'Probation',
                                              'bg-gray-100 text-gray-700': employee.status === 'On Leave' || employee.status === 'Terminated'
                                          }"
                                          x-text="employee.status"></span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-3 font-bold text-[10px] uppercase tracking-widest">
                                        <a :href="'/employees/' + employee.id" class="text-brand-600 hover:text-brand-700">View</a>
                                        <button @click="editEmployee(employee)" class="text-gray-600 hover:text-gray-900">Edit</button>
                                        <form :action="'/employees/' + employee.id" method="POST" @submit.prevent="if(confirm('Remove this employee record?')) $el.submit()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-500 hover:text-red-700">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-100 dark:border-white/[0.05]">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <p class="text-xs font-medium text-gray-500">
                        Showing <span x-text="startEntry"></span> to <span x-text="endEntry"></span> of <span x-text="totalEntries"></span> employees
                    </p>
                    <div class="flex items-center gap-1">
                        <button
                            @click="prevPage"
                            :disabled="currentPage === 1"
                            class="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-100 bg-white text-gray-700 shadow-theme-xs hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        </button>
                        <button
                            @click="nextPage"
                            :disabled="currentPage === totalPages"
                            class="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-100 bg-white text-gray-700 shadow-theme-xs hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
             MODALS (Teleported to Body for consistent centering)
             ══════════════════════════════════════════════════════ --}}
        <template x-teleport="body">
            <div x-show="openModal || editModal" x-cloak>
                
                {{-- ADD EMPLOYEE MODAL --}}
                <div x-show="openModal" 
                     class="fixed inset-0 z-[999999] flex items-center justify-center p-4"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95">
                    
                    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="openModal = false"></div>
                    
                    <div class="relative bg-white dark:bg-gray-900 rounded-[2rem] shadow-2xl w-full max-w-2xl overflow-hidden max-h-[95vh] overflow-y-auto transform transition-all" @click.stop>
                        <div class="px-8 py-6 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-white/[0.02]">
                            <h3 class="w-full text-center text-xl font-bold text-gray-800 dark:text-white uppercase tracking-widest">New Employee Registration</h3>
                        </div>
                        <form action="{{ route('employees.store') }}" method="POST" class="p-8 space-y-6">
                            @csrf
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="col-span-1">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">First Name</label>
                                    <input type="text" name="first_name" required placeholder="e.g. John"
                                        class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Last Name</label>
                                    <input type="text" name="last_name" required placeholder="e.g. Doe"
                                        class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Email Address</label>
                                    <input type="email" name="email" required placeholder="name@agency.com"
                                        class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Phone Number</label>
                                    <input type="text" name="phone" placeholder="(555) 000-0000" 
                                        class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Position</label>
                                    <select name="position" required class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                        <option value="Caregiver">Caregiver</option>
                                        <option value="Nurse">Nurse</option>
                                        <option value="Office Staff">Office Staff</option>
                                        <option value="Case Manager">Case Manager</option>
                                    </select>
                                </div>
                                <div class="col-span-1">
                                    <x-form.date-picker name="hire_date" label="Hire Date" placeholder="Select date" />
                                </div>
                                
                                <div class="col-span-2">
                                    <h5 class="text-[10px] font-black text-brand-500 uppercase tracking-widest mb-2 border-b border-gray-50 pb-2">CHAMPS Compliance</h5>
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">CHAMPS Username</label>
                                    <input type="text" name="champs_username" placeholder="e.g. USER1234" 
                                        class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                </div>
                                <div class="col-span-1">
                                    <x-form.date-picker name="champs_association_date" label="Association Date" placeholder="Select date" />
                                </div>

                                <div class="col-span-2">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Full Home Address</label>
                                    <input type="text" name="address" placeholder="123 Street, City, State" 
                                        class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                </div>
                            </div>
                            <div class="flex items-center gap-4 pt-6 mt-2 border-t border-gray-50 dark:border-gray-800">
                                <button type="button" @click="openModal = false" 
                                    class="flex-1 px-4 py-4 text-[11px] font-black text-gray-400 bg-gray-50 dark:bg-white/[0.02] rounded-xl hover:bg-gray-100 dark:hover:bg-white/[0.05] uppercase tracking-widest transition-all">
                                    Cancel
                                </button>
                                <button type="submit" 
                                    class="flex-1 px-4 py-4 text-[11px] font-black text-white bg-brand-500 rounded-xl hover:bg-brand-600 uppercase tracking-widest shadow-lg shadow-brand-500/20 transition-all transform active:scale-95">
                                    Register Employee
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- EDIT EMPLOYEE MODAL --}}
                <div x-show="editModal" 
                     class="fixed inset-0 z-[999999] flex items-center justify-center p-4"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95">
                    
                    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="editModal = false"></div>
                    
                    <div class="relative bg-white dark:bg-gray-900 rounded-[2rem] shadow-2xl w-full max-w-2xl overflow-hidden max-h-[95vh] overflow-y-auto transform transition-all" @click.stop>
                        <div class="px-8 py-6 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-white/[0.02]">
                            <h3 class="w-full text-center text-xl font-bold text-gray-800 dark:text-white uppercase tracking-widest">Update Employee Record</h3>
                        </div>
                        <form :action="'/employees/' + currentEmployee.id" method="POST" class="p-8 space-y-6">
                            @csrf
                            @method('PUT')
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="col-span-1">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">First Name</label>
                                    <input type="text" name="first_name" x-model="currentEmployee.first_name" required 
                                        class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Last Name</label>
                                    <input type="text" name="last_name" x-model="currentEmployee.last_name" required 
                                        class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Email Address</label>
                                    <input type="email" name="email" x-model="currentEmployee.email" required 
                                        class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Phone Number</label>
                                    <input type="text" name="phone" x-model="currentEmployee.phone" 
                                        class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Position</label>
                                    <select name="position" x-model="currentEmployee.position" required class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                        <option value="Caregiver">Caregiver</option>
                                        <option value="Nurse">Nurse</option>
                                        <option value="Office Staff">Office Staff</option>
                                        <option value="Case Manager">Case Manager</option>
                                    </select>
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Status</label>
                                    <select name="status_id" x-model="currentEmployee.status_id" class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                        @foreach($employeeStatuses as $st)
                                            <option value="{{ $st->id }}">{{ $st->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-1">
                                    <x-form.date-picker 
                                        id="edit-hire-picker"
                                        name="hire_date" 
                                        label="Hire Date" 
                                        @date-change="currentEmployee.hire_date = $event.detail.dateStr"
                                        />
                                </div>
                                <div class="col-span-1"></div>
                                
                                <div class="col-span-2">
                                    <h5 class="text-[10px] font-black text-brand-500 uppercase tracking-widest mb-2 border-b border-gray-50 pb-2">CHAMPS Compliance</h5>
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">CHAMPS Username</label>
                                    <input type="text" name="champs_username" x-model="currentEmployee.champs_username" 
                                        class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                </div>
                                <div class="col-span-1">
                                    <x-form.date-picker 
                                        id="edit-champs-picker"
                                        name="champs_association_date" 
                                        label="Association Date" 
                                        @date-change="currentEmployee.champs_association_date = $event.detail.dateStr"
                                        />
                                </div>

                                <div class="col-span-2">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 pl-1">Full Home Address</label>
                                    <input type="text" name="address" x-model="currentEmployee.address" 
                                        class="w-full px-5 py-4 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                </div>
                            </div>
                            <div class="flex items-center gap-4 pt-6 mt-2 border-t border-gray-50 dark:border-gray-800">
                                <button type="button" @click="editModal = false" 
                                    class="flex-1 px-4 py-4 text-[11px] font-black text-gray-400 bg-gray-50 dark:bg-white/[0.02] rounded-xl hover:bg-gray-100 dark:hover:bg-white/[0.05] uppercase tracking-widest transition-all">
                                    Cancel
                                </button>
                                <button type="submit" 
                                    class="flex-1 px-4 py-4 text-[11px] font-black text-white bg-brand-500 rounded-xl hover:bg-brand-600 uppercase tracking-widest shadow-lg shadow-brand-500/20 transition-all transform active:scale-95">
                                    Update Record
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </template>
    </div>
@endsection
