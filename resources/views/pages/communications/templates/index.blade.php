@extends('layouts.app')

@section('content')
<div class="rounded-2xl border border-gray-200 bg-white overflow-hidden" x-data="{ showAdd: false, showEdit: false, editTemplate: {} }">
    <div class="flex flex-col gap-4 px-6 py-5 border-b border-gray-200 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">Communication Templates</h2>
            <p class="text-sm text-gray-500 mt-0.5">Agency templates for email, fax, SMS, and internal messages. Use Case Coordinator (not ASW) labels.</p>
        </div>
        <button @click="showAdd = true" class="rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white">Add template</button>
    </div>

    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="px-6 py-3 text-left font-semibold text-gray-600">Name</th>
                <th class="px-6 py-3 text-left font-semibold text-gray-600">Channel</th>
                <th class="px-6 py-3 text-left font-semibold text-gray-600">Recipient strategy</th>
                <th class="px-6 py-3 text-left font-semibold text-gray-600">Status</th>
                <th class="px-6 py-3 text-right font-semibold text-gray-600">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($templates as $template)
                <tr>
                    <td class="px-6 py-4 font-semibold">{{ $template->name }}</td>
                    <td class="px-6 py-4 capitalize">{{ $template->channel }}</td>
                    <td class="px-6 py-4">{{ str_replace('_', ' ', $template->recipient_strategy) }}</td>
                    <td class="px-6 py-4">{{ $template->is_active ? 'Active' : 'Inactive' }}</td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <button type="button" @click="editTemplate = @js($template); showEdit = true" class="text-sm font-semibold text-gray-600">Edit</button>
                        <form method="POST" action="{{ route('communications.templates.toggle', $template) }}" class="inline">@csrf<button class="text-sm font-semibold text-amber-600">Toggle</button></form>
                        <form method="POST" action="{{ route('communications.templates.destroy', $template) }}" class="inline" onsubmit="return confirm('Delete template?')">@csrf @method('DELETE')<button class="text-sm font-semibold text-red-600">Delete</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">No templates yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    @include('pages.communications.templates._form-modal', ['modal' => 'showAdd', 'action' => route('communications.templates.store'), 'method' => 'POST'])
    @include('pages.communications.templates._form-modal', ['modal' => 'showEdit', 'action' => '', 'method' => 'PUT', 'edit' => true])
</div>
@endsection
