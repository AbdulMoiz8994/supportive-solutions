@extends('layouts.app')

@section('content')
@php
    $isEdit = !empty($template);
@endphp
<div class="max-w-2xl mx-auto space-y-6">

    <div>
        <a href="{{ route('forms') }}" class="text-[12px] font-semibold text-[#2563eb]">← Back to Forms</a>
        <h1 class="text-[24px] font-extrabold text-[#0f172a] mt-2">{{ $title }}</h1>
        <p class="text-[13px] text-[#64748b]">{{ $isEdit ? 'Update template settings and field definitions.' : 'Create a reusable form template for clients or caregivers.' }}</p>
    </div>

    <form method="POST"
          action="{{ $isEdit ? route('forms.templates.update', $template['id']) : route('forms.templates.store') }}"
          class="rounded-2xl border border-[#e6eef9] bg-white p-5 space-y-4">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div>
            <label class="block text-[12px] font-semibold text-[#64748b] mb-1">Name</label>
            <input type="text" name="name" required maxlength="255"
                   value="{{ old('name', $template['name'] ?? '') }}"
                   class="w-full rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
        </div>

        <div>
            <label class="block text-[12px] font-semibold text-[#64748b] mb-1">Description</label>
            <input type="text" name="description" maxlength="1000"
                   value="{{ old('description', $template['description'] ?? '') }}"
                   class="w-full rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
        </div>

        <div>
            <label class="block text-[12px] font-semibold text-[#64748b] mb-1">Target</label>
            <select name="target_type" required class="w-full rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
                <option value="client" @selected(old('target_type', $template['target_type'] ?? 'client') === 'client')>Client</option>
                <option value="employee" @selected(old('target_type', $template['target_type'] ?? '') === 'employee')>Caregiver</option>
            </select>
        </div>

        <div class="flex flex-wrap gap-4 text-[13px]">
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="requires_signature" value="1"
                       @checked(old('requires_signature', $template['requires_signature'] ?? true))>
                Requires signature
            </label>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="is_compliance_required" value="1"
                       @checked(old('is_compliance_required', $template['is_compliance_required'] ?? false))>
                Compliance required
            </label>
            @if($isEdit)
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', $template['is_active'] ?? true))>
                    Active
                </label>
            @endif
        </div>

        <div>
            <label class="block text-[12px] font-semibold text-[#64748b] mb-1">Fields (JSON)</label>
            <p class="text-[11px] text-[#94a3b8] mb-2">Array of objects with <code>key</code>, <code>label</code>, optional <code>type</code> / <code>readonly</code>.</p>
            <textarea name="fields" rows="10" required
                      class="w-full rounded-lg border border-[#e2e8f0] px-3 py-2 text-[12px] font-mono">{{ old('fields', $fieldsJson) }}</textarea>
        </div>

        <div class="flex flex-wrap gap-2 pt-2">
            <x-ui.btn variant="primary" type="submit">{{ $isEdit ? 'Save template' : 'Create template' }}</x-ui.btn>
            <x-ui.btn variant="outline" :href="route('forms')">Cancel</x-ui.btn>
        </div>
    </form>
</div>
@endsection
