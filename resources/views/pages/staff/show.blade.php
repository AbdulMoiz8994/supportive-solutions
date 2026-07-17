@extends('layouts.app')

@section('content')
@php
    use App\Support\TabbedPageTitle;

    $staffTabs = TabbedPageTitle::STAFF_TAB_LABELS;
@endphp
<div class="space-y-6 pb-20 w-full px-2" x-data="{
    activeTab: '{{ session('active_tab', 'profile') }}',
    staff: @js($staff),
    allRoles: @js($roles),
    currentRoleName: @js($staff->role),
    locations: @js($staff->locations),
    rolePermissions: [],
    tabs: @js($staffTabs),
    appName: @js(config('app.name', 'beydountech Home Care')),
    init() {
        this.updatePermissions();
        this.syncTitle();
    },
    switchTab(key) {
        this.activeTab = key;
        this.syncTitle();
    },
    syncTitle() {
        const label = this.tabs[this.activeTab] || 'Profile';
        document.title = label + ' — ' + this.staff.name + ' | ' + this.appName;
    },
    updatePermissions() {
        const role = this.allRoles.find(r => r.name === this.currentRoleName);
        this.rolePermissions = role ? role.permissions.map(p => String(p.id)) : [];
    },
    get selectedRole() {
        return this.allRoles.find(r => r.name === this.currentRoleName) || this.allRoles[0] || {};
    },
    async confirmFormSubmit(event, options) {
        event.preventDefault();
        await this.$store.dialog.confirmSubmit(event.target, options);
    },
    async confirmToggle() {
        const isDeactivate = this.staff.is_active;
        const ok = await this.$store.dialog.confirm({
            title: isDeactivate ? 'Deactivate this account?' : 'Activate this account?',
            message: isDeactivate
                ? `${this.staff.name} will lose access until reactivated.`
                : `${this.staff.name} will be able to sign in after activation.`,
            confirmLabel: isDeactivate ? 'Deactivate account' : 'Activate account',
            variant: isDeactivate ? 'danger' : 'primary',
        });

        if (ok) {
            document.getElementById('toggleStatusForm').submit();
        }
    },
}" x-init="$watch('currentRoleName', () => updatePermissions())">
    
    <!-- Page Header -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pt-4 px-2">
        <div class="space-y-1">
            <h1 class="text-[32px] font-bold text-[#1e293b] tracking-tight leading-tight" x-text="staff.name"></h1>
            <p class="text-[14px] font-medium text-[#64748b]">
                <span x-text="currentRoleName"></span> • <span x-text="locations[0]?.name || 'No Organization'"></span>
            </p>
        </div>
        
        <div class="flex items-center gap-3">
            <button x-show="activeTab !== 'activity'" @click="document.getElementById(activeTab + 'Form').submit()" 
                class="bg-[#2563eb] text-white px-8 py-2.5 rounded-xl text-[13px] font-bold tracking-wide shadow-lg shadow-[#2563eb]/20 hover:bg-[#1d4ed8] transition-all flex items-center gap-2">
                Save Changes
            </button>
        </div>
    </div>

    <!-- Main Content Card -->
    <div class="bg-[#eff6ff] rounded-[24px] border border-blue-100/50 overflow-hidden shadow-sm min-h-[600px]">
        <!-- Tab Navigation -->
        <div class="px-8 border-b border-blue-100/20 bg-blue-50/10">
            <div class="flex items-center gap-8">
                <button @click="switchTab('profile')"
                    :class="activeTab === 'profile' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-[#64748b] hover:text-[#1e293b]'"
                    class="py-5 text-[14px] font-bold transition-all outline-none">
                    Profile
                </button>
                <button @click="switchTab('permission')"
                    :class="activeTab === 'permission' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-[#64748b] hover:text-[#1e293b]'"
                    class="py-5 text-[14px] font-bold transition-all outline-none">
                    Permission
                </button>
                <button @click="switchTab('activity')"
                    :class="activeTab === 'activity' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-[#64748b] hover:text-[#1e293b]'"
                    class="py-5 text-[14px] font-bold transition-all outline-none">
                    Activity Log
                </button>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="p-8">
            <!-- Profile Tab -->
            <div x-show="activeTab === 'profile'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left Column: Personal Information -->
                    <div class="lg:col-span-2 space-y-8">
                        <form id="profileForm" :action="'/staff/' + staff.id" method="POST">
                            @csrf
                            @method('PUT')
                            <div>
                                <h3 class="text-[11px] font-black text-[#94a3b8] uppercase tracking-[0.1em] mb-6">Personal Information</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider ml-1">Full Name</label>
                                        <input type="text" name="name" x-model="staff.name" class="w-full px-5 py-3.5 bg-white border border-[#e2e8f0] rounded-2xl text-[14px] font-bold text-[#1e293b] focus:ring-4 focus:ring-blue-500/5 outline-none transition-all shadow-sm">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider ml-1">Job Title</label>
                                        <input type="text" :value="currentRoleName" readonly class="w-full px-5 py-3.5 bg-white/50 border border-[#e2e8f0] rounded-2xl text-[14px] font-bold text-[#64748b] outline-none transition-all cursor-not-allowed">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider ml-1">Email Address</label>
                                        <div class="relative">
                                            <svg class="w-4 h-4 text-[#94a3b8] absolute left-5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                            <input type="email" name="email" x-model="staff.email" class="w-full pl-12 pr-5 py-3.5 bg-white border border-[#e2e8f0] rounded-2xl text-[14px] font-bold text-[#1e293b] focus:ring-4 focus:ring-blue-500/5 outline-none transition-all shadow-sm">
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider ml-1">Phone Number</label>
                                        <div class="relative">
                                            <svg class="w-4 h-4 text-[#94a3b8] absolute left-5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                            <input type="text" name="phone" x-model="staff.phone" class="w-full pl-12 pr-5 py-3.5 bg-white border border-[#e2e8f0] rounded-2xl text-[14px] font-bold text-[#1e293b] focus:ring-4 focus:ring-blue-500/5 outline-none transition-all shadow-sm">
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider ml-1">Role</label>
                                        <select name="role" x-model="currentRoleName" class="w-full px-5 py-3.5 bg-white border border-[#e2e8f0] rounded-2xl text-[14px] font-bold text-[#1e293b] focus:ring-4 focus:ring-blue-500/5 outline-none transition-all appearance-none shadow-sm" style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2394a3b8%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1em;">
                                            @foreach($roles as $role)
                                                <option value="{{ $role->name }}">{{ $role->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider ml-1">Organization Access</label>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            @foreach($locations as $loc)
                                                <label class="flex items-center gap-3 p-3.5 bg-white border border-[#e2e8f0] rounded-2xl cursor-pointer hover:border-blue-200 transition-all group shadow-sm">
                                                    <input type="checkbox" name="location_ids[]" value="{{ $loc->id }}" 
                                                        :checked="locations.some(l => l.id === {{ $loc->id }})"
                                                        class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                    <span class="text-[13px] font-bold text-[#1e293b] group-hover:text-blue-600 transition-colors">{{ $loc->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Right Column: Account Info & Actions -->
                    <div class="space-y-8">
                        <!-- Account Info Card -->
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded-[24px] p-6 space-y-6">
                            <h4 class="text-[14px] font-bold text-[#1e293b]">Account Info</h4>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <svg class="w-4 h-4 text-[#94a3b8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        <span class="text-[13px] font-medium text-[#64748b]">Member since</span>
                                    </div>
                                    <span class="text-[13px] font-bold text-[#1e293b]" x-text="new Date(staff.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })"></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <svg class="w-4 h-4 text-[#94a3b8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span class="text-[13px] font-medium text-[#64748b]">Last activity</span>
                                    </div>
                                    <span class="text-[13px] font-bold text-[#1e293b]">
                                        @if($activityLogs->isNotEmpty())
                                            {{ $activityLogs->first()->created_at->diffForHumans() }}
                                        @else
                                            Never
                                        @endif
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <svg class="w-4 h-4 text-[#94a3b8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span class="text-[13px] font-medium text-[#64748b]">Status</span>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-green-100 text-green-600" x-show="staff.is_active">Active</span>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-red-100 text-red-600" x-show="!staff.is_active">Suspended</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <svg class="w-4 h-4 text-[#94a3b8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        <span class="text-[13px] font-medium text-[#64748b]">2FA Security</span>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider" 
                                        :class="staff.two_factor_verified_at ? 'bg-green-100 text-green-600' : 'bg-amber-100 text-amber-600'"
                                        x-text="staff.two_factor_verified_at ? 'Enabled' : 'Disabled'">
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Security Actions Card -->
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded-[24px] p-6 space-y-5">
                            <h4 class="text-[14px] font-bold text-[#1e293b]">Security Actions</h4>
                            <div class="space-y-3">
                                <form action="{{ route('staff.reset-password', $staff->id) }}" method="POST"
                                      @submit.prevent="confirmFormSubmit($event, {
                                          title: 'Send password reset email?',
                                          message: 'A secure reset link will be emailed to {{ $staff->email }}.',
                                          confirmLabel: 'Send reset link',
                                          variant: 'primary',
                                      })">
                                    @csrf
                                    <button type="submit" 
                                        class="w-full flex items-center justify-between p-4 bg-white border border-[#e2e8f0] rounded-2xl hover:border-blue-200 hover:bg-blue-50/30 transition-all group shadow-sm">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center text-[#64748b] group-hover:bg-blue-50 group-hover:text-blue-600 transition-all">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                            </div>
                                            <span class="text-[13px] font-bold text-[#1e293b]">Reset Password (Send Email)</span>
                                        </div>
                                        <svg class="w-4 h-4 text-[#94a3b8] group-hover:text-blue-600 transition-all translate-x-0 group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                </form>

                                <form action="{{ route('staff.revoke-sessions', $staff->id) }}" method="POST"
                                      @submit.prevent="confirmFormSubmit($event, {
                                          title: 'Revoke all active sessions?',
                                          message: '{{ $staff->name }} will be signed out on every device and must sign in again.',
                                          confirmLabel: 'Revoke sessions',
                                          variant: 'danger',
                                      })">
                                    @csrf
                                    <button type="submit"
                                        class="w-full flex items-center justify-between p-4 bg-white border border-[#e2e8f0] rounded-2xl hover:border-pink-200 hover:bg-pink-50/30 transition-all group shadow-sm">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center text-[#64748b] group-hover:bg-pink-50 group-hover:text-pink-600 transition-all">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                            </div>
                                            <span class="text-[13px] font-bold text-[#1e293b]">Revoke All Sessions</span>
                                        </div>
                                        <svg class="w-4 h-4 text-[#94a3b8] group-hover:text-pink-600 transition-all translate-x-0 group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                </form>

                                <button type="button" @click="confirmToggle()" class="w-full flex items-center justify-center px-5 py-3.5 bg-white border border-red-100 rounded-xl text-[13px] font-bold text-red-600 hover:bg-red-50 transition-all">
                                    <span x-text="staff.is_active ? 'Deactivate Account' : 'Activate Account'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <form id="toggleStatusForm" :action="'/staff/' + staff.id + '/toggle'" method="POST" class="hidden">
                    @csrf
                </form>
            </div>

            <!-- Permission Tab -->
            <div x-show="activeTab === 'permission'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <form id="permissionForm" :action="'/roles/' + selectedRole.id + '/permissions'" method="POST">
                    @csrf
                    <div class="space-y-8">
                        <h3 class="text-[11px] font-black text-[#94a3b8] uppercase tracking-[0.1em]">Permission Matrix</h3>
                        
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded-[24px] overflow-hidden">
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
                                                <p class="text-[12px] text-[#64748b]">Can view and manage {{ strtolower($permission->name) }} in the system</p>
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
                </form>
            </div>

            <!-- Activity Log Tab -->
            <div x-show="activeTab === 'activity'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="space-y-8">
                    <h3 class="text-[11px] font-black text-[#94a3b8] uppercase tracking-[0.1em]">Recent Activity</h3>
                    
                    <div class="space-y-4">
                        @forelse($activityLogs as $log)
                        <div class="flex items-start gap-4 p-5 bg-white border border-[#e2e8f0] rounded-2xl shadow-sm relative overflow-hidden group hover:border-blue-200 transition-all">
                            <div class="absolute left-0 top-0 bottom-0 w-1 {{ 
                                Str::contains($log->action, 'create') ? 'bg-green-500' : 
                                (Str::contains($log->action, 'update') ? 'bg-blue-500' : 
                                (Str::contains($log->action, 'delete') ? 'bg-red-500' : 'bg-purple-500'))
                            }}"></div>
                            
                            <div class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center shrink-0">
                                @if(Str::contains($log->action, 'create'))
                                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                @elseif(Str::contains($log->action, 'update'))
                                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                @else
                                    <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                @endif
                            </div>

                            <div class="flex-1 space-y-2">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-[14px] font-bold text-[#1e293b]">{{ $log->description ?: $log->action }}</h4>
                                    <span class="text-[12px] font-medium text-[#94a3b8]">{{ $log->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider {{ 
                                        Str::contains($log->action, 'create') ? 'bg-green-50 text-green-600' : 
                                        (Str::contains($log->action, 'update') ? 'bg-blue-50 text-blue-600' : 'bg-purple-50 text-purple-600')
                                    }}">
                                        {{ strtoupper(explode('_', $log->action)[0]) }}
                                    </span>
                                    <span class="text-[12px] text-[#64748b]">Organization • 2 min ago</span>
                                </div>
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-20 bg-white border border-[#e2e8f0] rounded-2xl shadow-sm">
                            <p class="text-[#64748b] text-[14px] font-medium">No activity logs found for this staff member.</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
    .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 10px;
    }
</style>
@endsection
