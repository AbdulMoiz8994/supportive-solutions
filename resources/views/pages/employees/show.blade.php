@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Employee Details" />

    <div class="p-6 bg-white rounded-xl dark:bg-white/[0.03] shadow-theme-xs" 
         x-data="{ activeTab: 'overview' }">
        
        <!-- Header Section -->
        <div class="flex flex-col gap-5 mb-8 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex items-center justify-center w-20 h-20 text-2xl font-bold uppercase bg-brand-500/10 text-brand-500 rounded-2xl">
                    {{ substr($employee->first_name, 0, 1) }}{{ substr($employee->last_name, 0, 1) }}
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                        {{ $employee->first_name }} {{ $employee->last_name }}
                    </h3>
                    <p class="flex items-center gap-2 mt-1 text-sm text-gray-500 dark:text-gray-400">
                        <span class="font-medium text-gray-700 dark:text-gray-300">Position:</span> {{ $employee->position ?? 'N/A' }}
                        <span class="mx-1">|</span>
                        <span class="font-medium text-gray-700 dark:text-gray-300">Hire Date:</span> {{ $employee->hire_date ?? 'N/A' }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="px-3 py-1 text-xs font-medium bg-green-100 text-green-700 rounded-full">
                    {{ $employee->status }}
                </span>
                <button class="px-4 py-2 text-sm font-medium text-white bg-brand-500 rounded-lg hover:bg-brand-600 transition-colors">
                    Edit Employee
                </button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="mb-8 border-b border-gray-100 dark:border-white/[0.05]">
            <nav class="flex flex-wrap gap-x-8 gap-y-4">
                <template x-for="tab in [
                    { id: 'overview', label: 'Overview' },
                    { id: 'details', label: 'Employee Details' },
                    { id: 'schedule', label: 'Schedule' },
                    { id: 'clients', label: 'Assigned Clients' },
                    { id: 'compliance', label: 'Compliance' },
                    { id: 'documents', label: 'Documents' },
                    { id: 'billing', label: 'Payroll & Billing' }
                ]" :key="tab.id">
                    <button 
                        @click="activeTab = tab.id"
                        :class="activeTab === tab.id ? 'border-brand-500 text-brand-500' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-white'"
                        class="pb-4 text-sm font-medium border-b-2 transition-all whitespace-nowrap"
                        x-text="tab.label"
                    ></button>
                </template>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="relative min-h-[400px]">
            
            <!-- Overview Tab -->
            <div x-show="activeTab === 'overview'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Stats Card -->
                    <div class="p-5 border border-gray-100 rounded-2xl dark:border-white/[0.05]">
                        <h4 class="mb-4 text-sm font-semibold text-gray-500 uppercase">Weekly Hours</h4>
                        <p class="text-3xl font-bold text-gray-800 dark:text-white/90">0.00</p>
                        <p class="mt-2 text-xs text-brand-500 font-medium">Synced from HHAExchange</p>
                    </div>
                    <div class="p-5 border border-gray-100 rounded-2xl dark:border-white/[0.05]">
                        <h4 class="mb-4 text-sm font-semibold text-gray-500 uppercase">Assigned Clients</h4>
                        <p class="text-3xl font-bold text-gray-800 dark:text-white/90">{{ $employee->clients->count() }}</p>
                        <p class="mt-2 text-xs text-gray-500">Active assignments</p>
                    </div>
                    <div class="p-5 border border-gray-100 rounded-2xl dark:border-white/[0.05] bg-brand-500/[0.02]">
                        <h4 class="mb-4 text-sm font-semibold text-brand-600 uppercase">CHAMPs Association</h4>
                        @if($employee->champs_association_date)
                            <p class="text-lg font-bold text-gray-800 dark:text-white/90">{{ $employee->champs_association_date }}</p>
                        @else
                            <p class="text-lg font-bold text-yellow-600">Pending</p>
                        @endif
                        <p class="mt-2 text-xs text-gray-500">State of Michigan</p>
                    </div>
                </div>
            </div>

            <!-- Details Tab -->
            <div x-show="activeTab === 'details'" style="display: none;">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-8">
                        <div>
                            <h4 class="mb-4 text-xs font-bold text-gray-400 uppercase tracking-widest">Internal Details</h4>
                            <div class="space-y-4">
                                <div class="flex justify-between py-3 border-b border-gray-50 dark:border-white/[0.02]">
                                    <span class="text-gray-500">First Name</span>
                                    <span class="font-medium text-gray-800 dark:text-white/90">{{ $employee->first_name }}</span>
                                </div>
                                <div class="flex justify-between py-3 border-b border-gray-50 dark:border-white/[0.02]">
                                    <span class="text-gray-500">Last Name</span>
                                    <span class="font-medium text-gray-800 dark:text-white/90">{{ $employee->last_name }}</span>
                                </div>
                                <div class="flex justify-between py-3 border-b border-gray-50 dark:border-white/[0.02]">
                                    <span class="text-gray-500">Position</span>
                                    <span class="font-medium text-gray-800 dark:text-white/90">{{ $employee->position ?? 'N/A' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h4 class="mb-4 text-xs font-bold text-gray-400 uppercase tracking-widest">Contact Information</h4>
                        <div class="space-y-4">
                            <div class="flex justify-between py-3 border-b border-gray-50 dark:border-white/[0.02]">
                                <span class="text-gray-500">Phone</span>
                                <span class="font-medium text-gray-800 dark:text-white/90">{{ $employee->phone ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between py-3 border-b border-gray-50 dark:border-white/[0.02]">
                                <span class="text-gray-500">Email</span>
                                <span class="font-medium text-gray-800 dark:text-white/90">{{ $employee->email ?? 'N/A' }}</span>
                            </div>
                            <div class="py-3">
                                <span class="text-gray-500">Address</span>
                                <p class="mt-2 font-medium text-gray-800 dark:text-white/90">{{ $employee->address ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedule Tab -->
            <div x-show="activeTab === 'schedule'" style="display: none;">
                <div class="rounded-2xl border border-gray-100 p-5 dark:border-white/[0.05]">
                    <h4 class="mb-4 text-sm font-semibold uppercase text-gray-500">Schedule & Appointments</h4>
                    @if ($employee->schedules->isEmpty())
                        <p class="text-sm text-gray-500">No schedule events linked to this employee.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 text-[10px] uppercase tracking-widest text-gray-400">
                                        <th class="px-3 py-2">Date</th>
                                        <th class="px-3 py-2">Title</th>
                                        <th class="px-3 py-2">Client</th>
                                        <th class="px-3 py-2">Status</th>
                                        <th class="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($employee->schedules->sortByDesc(fn ($s) => $s->start_at ?? $s->date) as $event)
                                        <tr class="border-b border-gray-50 dark:border-white/[0.02]">
                                            <td class="px-3 py-3">{{ ($event->start_at ?? $event->date)?->format('M j, Y') }}</td>
                                            <td class="px-3 py-3 font-semibold">{{ $event->title }}</td>
                                            <td class="px-3 py-3">{{ $event->client ? $event->client->first_name.' '.$event->client->last_name : '—' }}</td>
                                            <td class="px-3 py-3">{{ $event->status }}</td>
                                            <td class="px-3 py-3 text-right">
                                                <a href="{{ route('schedule.show', $event->id) }}" class="font-semibold text-brand-600">View</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Assigned Clients Tab -->
            <div x-show="activeTab === 'clients'" style="display: none;">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @forelse($employee->clients as $client)
                        <div class="p-5 border border-gray-100 rounded-2xl dark:border-white/[0.05] hover:border-brand-500/20 transition-all">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-10 h-10 flex items-center justify-center bg-brand-500/10 text-brand-500 rounded-xl font-bold lowercase">
                                    {{ substr($client->first_name, 0, 1) }}{{ substr($client->last_name, 0, 1) }}
                                </div>
                                <div>
                                    <h5 class="font-semibold text-gray-800 dark:text-white/90 underline">
                                        <a href="{{ route('clients.show', $client->id) }}">{{ $client->first_name }} {{ $client->last_name }}</a>
                                    </h5>
                                    <span class="text-xs text-gray-400">{{ $client->county }} County</span>
                                </div>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="px-2 py-1 bg-gray-50 text-gray-600 rounded-md text-xs dark:bg-white/5">{{ $client->status }}</span>
                                <span class="text-brand-500 font-medium">View Info</span>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full p-10 text-center border-2 border-dashed border-gray-100 rounded-2xl">
                            <p class="text-gray-500">No clients assigned to this caregiver.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Compliance Tab -->
            <div x-show="activeTab === 'compliance'" style="display: none;">
                <div class="max-w-2xl space-y-6">
                    <div class="p-5 border border-gray-100 rounded-2xl dark:border-white/[0.05]">
                        <h4 class="mb-5 text-sm font-semibold text-gray-800 uppercase dark:text-white/90">CHAMPs Association</h4>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500">Champs Username</span>
                                <span class="font-medium text-gray-800 dark:text-white/90">{{ $employee->champs_username ?? 'Not set' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500">Association Date</span>
                                <span class="font-medium text-gray-800 dark:text-white/90">{{ $employee->champs_association_date ?? 'Pending' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="p-5 border border-gray-100 rounded-2xl dark:border-white/[0.05]">
                        <h4 class="mb-5 text-sm font-semibold text-gray-800 uppercase dark:text-white/90">Michigan Background Check</h4>
                        <div class="p-10 text-center text-gray-400 bg-gray-50 rounded-xl dark:bg-white/[0.01]">
                            <p>Compliance checklists will be integrated here.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents Tab -->
            <div x-show="activeTab === 'documents'" style="display: none;">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    @forelse($employee->documents as $doc)
                        <div class="group relative p-4 border border-gray-100 rounded-2xl hover:shadow-theme-xs transition-all dark:border-white/[0.05]">
                            <div class="w-full aspect-[3/4] mb-4 bg-gray-50 rounded-xl flex items-center justify-center dark:bg-white/[0.02]">
                                <svg class="w-12 h-12 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path></svg>
                            </div>
                            <p class="text-sm font-medium text-gray-800 truncate dark:text-white/90">{{ $doc->name }}</p>
                            <p class="text-xs text-gray-500 mt-1 uppercase">{{ $doc->type }}</p>
                        </div>
                    @empty
                        <div class="col-span-full p-20 text-center border-2 border-dashed border-gray-100 rounded-2xl">
                            <p class="text-gray-500">No documents uploaded.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Billing Tab -->
            <div x-show="activeTab === 'billing'" style="display: none;">
                <div class="p-20 text-center border-2 border-dashed border-gray-100 rounded-2xl">
                    <h5 class="font-semibold text-gray-800 dark:text-white/90">Payroll Integration</h5>
                    <p class="text-gray-500 mt-2">Connect to HHAExchange or QuickBooks for automatic payroll sync.</p>
                </div>
            </div>

        </div>
    </div>
@endsection
