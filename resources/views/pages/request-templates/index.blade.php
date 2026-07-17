@extends('layouts.app')

@section('content')
<div class="rounded-2xl border border-gray-200 bg-white overflow-hidden"
     x-data="{
        showAddModal: false,
        showEditModal: false,
        editTemplate: {},
        openEdit(template) {
            this.editTemplate = { ...template };
            this.showEditModal = true;
        }
     }">

    <div class="flex flex-col gap-4 px-6 py-5 border-b border-gray-200 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">Request Templates</h2>
            <p class="text-sm text-gray-500 mt-0.5">Configure agency Send Request templates for Case Coordinator, PCP, and custom recipients.</p>
        </div>
        <button @click="showAddModal = true"
            class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            Add Template
        </button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="bg-gray-50/80 border-b border-gray-200">
                    <th class="px-6 py-3.5 font-semibold text-gray-600 text-xs uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3.5 font-semibold text-gray-600 text-xs uppercase tracking-wider">Delivery</th>
                    <th class="px-6 py-3.5 font-semibold text-gray-600 text-xs uppercase tracking-wider">Recipient</th>
                    <th class="px-6 py-3.5 font-semibold text-gray-600 text-xs uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3.5 font-semibold text-gray-600 text-xs uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($templates as $template)
                <tr class="hover:bg-gray-50/70 transition-colors">
                    <td class="px-6 py-4">
                        <div class="font-semibold text-gray-800">{{ $template->name }}</div>
                        @if($template->category)
                            <div class="text-xs text-gray-400 mt-0.5">{{ $template->category }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-gray-600 capitalize">{{ str_replace('_', ' ', $template->delivery_method) }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ str_replace('_', ' ', $template->recipient_type) }}</td>
                    <td class="px-6 py-4">
                        @if($template->is_active)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-green-100 text-green-700">Active</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-gray-100 text-gray-700">Inactive</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button type="button" @click="openEdit(@js($template))"
                                class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50">Edit</button>
                            <form method="POST" action="{{ route('request-templates.toggle', $template->id) }}">
                                @csrf
                                <button type="submit" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold {{ $template->is_active ? 'text-amber-600 hover:bg-amber-50' : 'text-green-600 hover:bg-green-50' }}">
                                    {{ $template->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">No request templates configured yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/50 text-xs text-gray-500">
        Placeholders: <code class="text-gray-700">@{{ client_name }}</code>,
        <code class="text-gray-700">@{{ member_id }}</code>,
        <code class="text-gray-700">@{{ case_coordinator_name }}</code>,
        <code class="text-gray-700">@{{ pcp_name }}</code>,
        <code class="text-gray-700">@{{ agency_name }}</code>
    </div>
</div>

@php
    $deliveryMethods = \App\Models\RequestTemplate::deliveryMethods();
    $recipientTypes = \App\Models\RequestTemplate::recipientTypes();
@endphp

<template x-teleport="body">
    <div x-show="showAddModal || showEditModal" x-cloak>
        <div x-show="showAddModal" class="fixed inset-0 z-[999999] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="showAddModal = false"></div>
            <div class="relative w-full max-w-2xl bg-white rounded-[24px] shadow-2xl overflow-hidden max-h-[90vh] flex flex-col" @click.stop>
                <div class="px-8 py-6 border-b border-gray-50 flex justify-between items-start">
                    <div>
                        <h3 class="text-[20px] font-bold text-[#1e293b]">New Request Template</h3>
                        <p class="text-[13px] text-[#64748b]">Create a reusable Send Request template for your agency.</p>
                    </div>
                    <button @click="showAddModal = false" class="w-8 h-8 rounded-full border border-gray-100 flex items-center justify-center text-gray-400 hover:bg-gray-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('request-templates.store') }}" class="p-8 space-y-5 overflow-y-auto">
                    @csrf
                    @if(auth()->user()->isSuperAdmin() && !auth()->user()->organization_id && $organizations->isNotEmpty())
                    <div>
                        <label class="block text-[13px] font-bold text-[#1e293b] mb-2">Organization</label>
                        <select name="organization_id" required class="w-full px-4 py-3 rounded-[10px] border border-[#e2e8f0] text-[13px]">
                            @foreach($organizations as $organization)
                                <option value="{{ $organization->id }}">{{ $organization->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    @include('pages.request-templates._form-fields', ['prefix' => 'create'])
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" @click="showAddModal = false" class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600">Cancel</button>
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-brand-500 text-white text-sm font-semibold hover:bg-brand-600">Save Template</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="showEditModal" class="fixed inset-0 z-[999999] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="showEditModal = false"></div>
            <div class="relative w-full max-w-2xl bg-white rounded-[24px] shadow-2xl overflow-hidden max-h-[90vh] flex flex-col" @click.stop>
                <div class="px-8 py-6 border-b border-gray-50 flex justify-between items-start">
                    <div>
                        <h3 class="text-[20px] font-bold text-[#1e293b]">Edit Request Template</h3>
                        <p class="text-[13px] text-[#64748b]">Update template content and delivery settings.</p>
                    </div>
                    <button @click="showEditModal = false" class="w-8 h-8 rounded-full border border-gray-100 flex items-center justify-center text-gray-400 hover:bg-gray-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form method="POST" :action="`{{ url('/request-templates') }}/${editTemplate.id}`" class="p-8 space-y-5 overflow-y-auto">
                    @csrf
                    @method('PUT')
                    @include('pages.request-templates._form-fields', ['prefix' => 'edit', 'alpine' => true])
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" @click="showEditModal = false" class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600">Cancel</button>
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-brand-500 text-white text-sm font-semibold hover:bg-brand-600">Update Template</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
@endsection
