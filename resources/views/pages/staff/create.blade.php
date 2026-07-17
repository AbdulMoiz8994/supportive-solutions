@extends('layouts.app')

@section('content')
<div class="space-y-6 pb-20 w-full px-2" x-data="{ 
    currentStep: 1,
    activeTab: 'profile',
    staff: {
        name: '{{ old('name') }}',
        email: '{{ old('email') }}',
        phone: '{{ old('phone') }}',
        role: '{{ old('role') }}'
    },
    allRoles: @js($roles),
    currentRoleName: '{{ old('role') }}',
    locations: @js($locations),
    rolePermissions: @js(old('permissions', [])),
    
    init() {
        if (this.currentRoleName && this.rolePermissions.length === 0) {
            this.updatePermissions();
        }
    },
    updatePermissions() {
        const role = this.allRoles.find(r => r.name === this.currentRoleName);
        this.rolePermissions = role ? role.permissions.map(p => String(p.id)) : [];
    },
    get selectedRole() {
        return this.allRoles.find(r => r.name === this.currentRoleName) || {};
    },
    nextStep() {
        if (this.currentStep === 1) {
            if (!this.staff.name || !this.staff.email || !this.currentRoleName) {
                this.$store.dialog.alert({
                    title: 'Missing required fields',
                    message: 'Please fill in Name, Email, and Role before continuing to permissions.',
                    variant: 'warning',
                    confirmLabel: 'OK, I\'ll complete them',
                });
                return;
            }
            this.currentStep = 2;
            this.activeTab = 'permission';
        }
    },
    prevStep() {
        if (this.currentStep === 2) {
            this.currentStep = 1;
            this.activeTab = 'profile';
        }
    }
}" x-init="$watch('currentRoleName', () => updatePermissions())">
    
    <form id="createForm" action="{{ route('staff.store') }}" method="POST">
        @csrf
        
        <!-- Page Header -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pt-4 px-2">
            <div class="space-y-1">
                <h1 class="text-[32px] font-bold text-[#1e293b] tracking-tight leading-tight">New Staff Enrollment</h1>
                <p class="text-[14px] font-medium text-[#64748b]">
                    Step <span x-text="currentStep"></span> of 2: <span x-text="currentStep === 1 ? 'Personal Details' : 'Access Permissions'"></span>
                </p>
            </div>
            
            <div class="flex items-center gap-3">
                <a href="{{ route('staff.index', ['tab' => 'staff']) }}" x-show="currentStep === 1" class="px-6 py-2.5 rounded-xl border border-gray-200 text-[13px] font-bold text-[#64748b] hover:bg-gray-50 transition-all">
                    Cancel
                </a>
                <button type="button" @click="prevStep" x-show="currentStep === 2" class="px-6 py-2.5 rounded-xl border border-gray-200 text-[13px] font-bold text-[#64748b] hover:bg-gray-50 transition-all">
                    Back to Details
                </button>
                
                <button type="button" @click="nextStep" x-show="currentStep === 1"
                    class="bg-[#2563eb] text-white px-8 py-2.5 rounded-xl text-[13px] font-bold tracking-wide shadow-lg shadow-[#2563eb]/20 hover:bg-[#1d4ed8] transition-all flex items-center gap-2">
                    Configure Permissions
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>

                <button type="submit" x-show="currentStep === 2"
                    class="bg-green-600 text-white px-8 py-2.5 rounded-xl text-[13px] font-bold tracking-wide shadow-lg shadow-green-600/20 hover:bg-green-700 transition-all flex items-center gap-2">
                    Create & Onboard Staff
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                </button>
            </div>
        </div>

        <!-- Main Content Card -->
        <div class="bg-[#eff6ff] rounded-[24px] border border-blue-100/50 overflow-hidden shadow-sm min-h-[600px] mt-6">
            <!-- Tab Navigation (Visual Only) -->
            <div class="px-8 border-b border-blue-100/20 bg-blue-50/10">
                <div class="flex items-center gap-8">
                    <button type="button" @click="prevStep" 
                        :class="currentStep === 1 ? 'text-blue-600 border-b-2 border-blue-600' : 'text-[#64748b]'"
                        class="py-5 text-[14px] font-bold transition-all outline-none">
                        1. Profile Information
                    </button>
                    <button type="button" @click="nextStep"
                        :class="currentStep === 2 ? 'text-blue-600 border-b-2 border-blue-600' : 'text-[#64748b]'"
                        class="py-5 text-[14px] font-bold transition-all outline-none">
                        2. Security & Permissions
                    </button>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="p-8">
                <!-- Step 1: Profile Information -->
                <div x-show="currentStep === 1" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-2 space-y-8">
                            <div>
                                <h3 class="text-[11px] font-black text-[#94a3b8] uppercase tracking-[0.1em] mb-6">Personal Information</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider ml-1">Full Name</label>
                                        <input type="text" name="name" x-model="staff.name" required placeholder="Agency Name" class="w-full px-5 py-3.5 bg-white border border-[#e2e8f0] rounded-2xl text-[14px] font-bold text-[#1e293b] focus:ring-4 focus:ring-blue-500/5 outline-none transition-all shadow-sm">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider ml-1">Primary Role</label>
                                        <select name="role" x-model="currentRoleName" required class="w-full px-5 py-3.5 bg-white border border-[#e2e8f0] rounded-2xl text-[14px] font-bold text-[#1e293b] focus:ring-4 focus:ring-blue-500/5 outline-none transition-all appearance-none shadow-sm" style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2394a3b8%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1em;">
                                            <option value="" disabled selected>Select a role</option>
                                            @foreach($roles as $role)
                                                <option value="{{ $role->name }}">{{ $role->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider ml-1">Email Address</label>
                                        <div class="relative">
                                            <svg class="w-4 h-4 text-[#94a3b8] absolute left-5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                            <input type="email" name="email" x-model="staff.email" required placeholder="john@example.com" class="w-full pl-12 pr-5 py-3.5 bg-white border border-[#e2e8f0] rounded-2xl text-[14px] font-bold text-[#1e293b] focus:ring-4 focus:ring-blue-500/5 outline-none transition-all shadow-sm">
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider ml-1">Phone Number</label>
                                        <div class="relative">
                                            <svg class="w-4 h-4 text-[#94a3b8] absolute left-5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                            <input type="text" name="phone" x-model="staff.phone" placeholder="(555) 000-0000" class="w-full pl-12 pr-5 py-3.5 bg-white border border-[#e2e8f0] rounded-2xl text-[14px] font-bold text-[#1e293b] focus:ring-4 focus:ring-blue-500/5 outline-none transition-all shadow-sm">
                                        </div>
                                    </div>
                                    <div class="md:col-span-2 space-y-4">
                                        <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider ml-1">Assign Locations</label>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                            @foreach($locations as $loc)
                                                <label class="flex items-center gap-3 p-3.5 bg-white border border-[#e2e8f0] rounded-2xl cursor-pointer hover:border-blue-200 transition-all group shadow-sm">
                                                    <input type="checkbox" name="location_ids[]" value="{{ $loc->id }}" 
                                                        {{ is_array(old('location_ids')) && in_array($loc->id, old('location_ids')) ? 'checked' : '' }}
                                                        class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                    <span class="text-[13px] font-bold text-[#1e293b] group-hover:text-blue-600 transition-colors">{{ $loc->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-8">
                            <div class="bg-white border border-[#e2e8f0] rounded-[24px] p-6 space-y-6 shadow-sm">
                                <div class="flex items-center gap-4 mb-2">
                                    <div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-600">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <div>
                                        <h4 class="text-[14px] font-bold text-[#1e293b]">Quick Note</h4>
                                        <p class="text-[12px] text-[#64748b]">Onboarding Workflow</p>
                                    </div>
                                </div>
                                <p class="text-[13px] text-[#64748b] leading-relaxed">
                                    You are currently in Step 1. After filling these details, you will proceed to configure specific system access permissions.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Permissions Matrix -->
                <div x-show="currentStep === 2" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="space-y-8">
                        <div class="flex items-center justify-between">
                            <h3 class="text-[11px] font-black text-[#94a3b8] uppercase tracking-[0.1em]">Custom Permission Matrix</h3>
                            <div class="px-4 py-1.5 bg-blue-50 rounded-lg">
                                <span class="text-[11px] font-bold text-blue-600">Inheriting from <span x-text="currentRoleName"></span></span>
                            </div>
                        </div>
                        
                        <div class="bg-white border border-[#e2e8f0] rounded-[24px] overflow-hidden shadow-sm">
                            <div class="divide-y divide-[#e2e8f0]">
                                @foreach($modules as $module => $permissions)
                                    @foreach($permissions as $permission)
                                    <div class="flex items-center justify-between p-6 bg-white hover:bg-[#f8fafc] transition-colors">
                                        <div class="flex items-center gap-6">
                                            <div class="w-24 shrink-0">
                                                <span class="px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest {{ 
                                                    $module === 'Clients' ? 'bg-blue-50 text-blue-600' : 
                                                    ($module === 'Finance' ? 'bg-amber-50 text-amber-600' : 
                                                    ($module === 'Admin' ? 'bg-purple-50 text-purple-600' : 'bg-gray-50 text-gray-600')) 
                                                }}">
                                                    {{ $module }}
                                                </span>
                                            </div>
                                            <div class="space-y-0.5">
                                                <h4 class="text-[14px] font-bold text-[#1e293b]">{{ $permission->name }}</h4>
                                                <p class="text-[12px] text-[#64748b]">Can access {{ strtolower($permission->name) }} features</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <label class="flex items-center cursor-pointer group">
                                                <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" 
                                                    x-model="rolePermissions"
                                                    class="hidden">
                                                <div class="flex items-center gap-2 px-4 py-2 rounded-xl transition-all"
                                                    :class="rolePermissions.includes('{{ $permission->id }}') ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-400'">
                                                    <div class="w-1.5 h-1.5 rounded-full transition-all"
                                                        :class="rolePermissions.includes('{{ $permission->id }}') ? 'bg-green-500' : 'bg-gray-400'"></div>
                                                    <span class="text-[11px] font-black uppercase tracking-wider" x-text="rolePermissions.includes('{{ $permission->id }}') ? 'Allow' : 'Deny'"></span>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    @endforeach
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>
@endsection
