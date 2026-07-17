@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-common.page-breadcrumb pageTitle="Clinical Audit Trail" />
        
        <div class="flex items-center gap-2 text-theme-xs font-semibold text-gray-500 uppercase tracking-widest">
            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
            Real-time Monitoring Active
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6">
        <!-- Audit Timeline -->
        <div class="col-span-12 lg:col-span-8">
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-6 lg:p-8">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white/90 mb-8">System Activity Stream</h3>

                <div class="relative space-y-8 before:absolute before:left-[17px] before:top-2 before:h-[calc(100%-16px)] before:w-0.5 before:bg-gray-100 dark:before:bg-gray-800">
                    @forelse($activities as $activity)
                        <div class="relative pl-12">
                            <!-- Icon/Marker -->
                            <div class="absolute left-0 top-0 flex h-9 w-9 items-center justify-center rounded-full bg-white dark:bg-gray-900 border-2 {{ str_contains($activity->action, 'Created') ? 'border-brand-500' : (str_contains($activity->action, 'Deleted') ? 'border-red-500' : 'border-orange-500') }} z-10">
                                @if(str_contains($activity->action, 'Created'))
                                    <svg class="w-4 h-4 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"></path></svg>
                                @elseif(str_contains($activity->action, 'Deleted'))
                                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                @else
                                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                @endif
                            </div>

                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 rounded-xl border border-gray-100 dark:border-white/5 bg-gray-50/50 dark:bg-white/[0.01] hover:shadow-theme-xs transition-all">
                                <div>
                                    <span class="text-xs font-bold uppercase tracking-wider {{ str_contains($activity->action, 'Created') ? 'text-brand-600' : (str_contains($activity->action, 'Deleted') ? 'text-red-600' : 'text-orange-600') }}">
                                        {{ $activity->action }}
                                    </span>
                                    <p class="mt-1 text-sm font-medium text-gray-800 dark:text-white/90">
                                        {{ $activity->description }}
                                    </p>
                                    <div class="mt-2 flex items-center gap-3">
                                        <div class="flex items-center gap-1.5 text-[10px] text-gray-500 font-semibold uppercase">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                            {{ $activity->user?->first_name ?? 'System' }} 
                                        </div>
                                        <div class="w-1 h-1 rounded-full bg-gray-300"></div>
                                        <div class="text-[10px] text-gray-400 font-medium">
                                            IP: {{ $activity->ip_address }}
                                        </div>
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter">
                                        {{ $activity->created_at->format('M d, Y') }}
                                    </span>
                                    <div class="text-xs font-bold text-gray-800 dark:text-white/90">
                                        {{ $activity->created_at->format('h:i A') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center py-20 text-center">
                            <div class="p-4 bg-gray-50 dark:bg-white/5 rounded-full mb-4 text-gray-400">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            </div>
                            <h4 class="text-gray-800 dark:text-white/90 font-bold">No Audit Records Found</h4>
                            <p class="text-sm text-gray-500 mt-1 max-w-xs">Start modifying data (Intakes/Clients/Visits) to see real-time logs here.</p>
                        </div>
                    @endforelse
                </div>

                <div class="mt-10">
                    {{ $activities->links() }}
                </div>
            </div>
        </div>

        <!-- Compliance Sidebar -->
        <div class="col-span-12 lg:col-span-4 space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-6 shadow-theme-xs">
                <h4 class="text-base font-bold text-gray-800 dark:text-white/90 mb-4">Security Overview</h4>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 rounded-lg bg-green-50 dark:bg-green-500/10 border border-green-100 dark:border-green-500/20">
                        <span class="text-xs font-bold text-green-700 dark:text-green-400 uppercase tracking-wider">Multi-Tenancy</span>
                        <span class="px-2 py-0.5 rounded bg-green-500 text-white text-[9px] font-black uppercase">Active</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-lg bg-brand-50 dark:bg-brand-500/10 border border-brand-100 dark:border-brand-500/20">
                        <span class="text-xs font-bold text-brand-700 dark:text-brand-400 uppercase tracking-wider">Audit Depth</span>
                        <span class="text-xs font-bold text-gray-800 dark:text-white/90 uppercase">Full History</span>
                    </div>
                </div>
                <p class="mt-4 text-[10px] text-gray-500 italic leading-relaxed">
                    This trail is tamper-proof. All actions are logged with timestamp and origin IP for state health department compliance.
                </p>
            </div>

            <!-- Quick Stats -->
            <div class="rounded-2xl border border-gray-200 bg-brand-600 p-6 shadow-theme-xs text-white">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-white/20 rounded-lg backdrop-blur-md">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <h4 class="text-base font-bold">Audit Policy</h4>
                </div>
                <p class="text-xs text-brand-100 leading-relaxed mb-6">
                    Audit logs are retained for 7 years to comply with federal healthcare data regulations.
                </p>
                <div class="h-1 w-full bg-white/20 rounded-full overflow-hidden">
                    <div class="h-full bg-white w-full"></div>
                </div>
            </div>
        </div>
    </div>
@endsection
