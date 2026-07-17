@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <a href="{{ route('staff.index') }}" class="text-[13px] text-[#2563eb] font-semibold hover:underline">‹ AI Agents</a>
        <h1 class="text-[28px] font-extrabold text-[#0f172a] tracking-tight mt-1">Add AI Agent</h1>
        <p class="text-[13px] text-[#64748b] mt-1.5">Creates a registry entry and provisions a platform user with scoped permissions.</p>
    </div>

    @if($errors->any())
        <x-ui.alert variant="error">
            <ul class="list-disc list-inside space-y-0.5 text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <form action="{{ route('staff.agents.store') }}" method="POST" class="rounded-xl border border-[#e2e8f0] bg-white p-6 space-y-5">
        @csrf

        <div>
            <label class="block text-[11px] font-bold uppercase tracking-wide text-[#64748b] mb-1.5">Agent name</label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full px-3 py-2.5 text-sm border border-[#e2e8f0] rounded-lg">
            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wide text-[#64748b] mb-1.5">Slug (optional)</label>
                <input type="text" name="slug" value="{{ old('slug') }}" placeholder="auto-from-name"
                       class="w-full px-3 py-2.5 text-sm border border-[#e2e8f0] rounded-lg">
                @error('slug')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wide text-[#64748b] mb-1.5">Start from template</label>
                <select name="template_slug" class="w-full px-3 py-2.5 text-sm border border-[#e2e8f0] rounded-lg">
                    <option value="">Blank custom agent</option>
                    @foreach($formOptions['catalog_templates'] as $tpl)
                        <option value="{{ $tpl['slug'] }}" @selected(old('template_slug') === $tpl['slug'])>{{ $tpl['name'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-[11px] font-bold uppercase tracking-wide text-[#64748b] mb-1.5">Role description</label>
            <input type="text" name="role_description" value="{{ old('role_description') }}"
                   class="w-full px-3 py-2.5 text-sm border border-[#e2e8f0] rounded-lg">
        </div>

        <div>
            <label class="block text-[11px] font-bold uppercase tracking-wide text-[#64748b] mb-2">Default autonomy</label>
            @include('pages.staff-ai-agents.partials.autonomy-mode-picker', [
                'modes' => $formOptions['autonomy_modes'],
                'name' => 'autonomy_mode',
                'value' => old('autonomy_mode', 'approval_required'),
            ])
        </div>

        <div>
            <label class="block text-[11px] font-bold uppercase tracking-wide text-[#64748b] mb-2">Program scope</label>
            @php $defaultPrograms = old('scope_programs', config('ai_agent_registry.programs', [])); @endphp
            <div class="flex flex-wrap gap-3">
                @foreach($formOptions['programs'] as $program)
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="scope_programs[]" value="{{ $program }}"
                               @checked(collect($defaultPrograms)->contains($program)) class="rounded border-[#cbd5e1]">
                        {{ $program }}
                    </label>
                @endforeach
            </div>
        </div>

        @if($formOptions['locations']->isNotEmpty())
            @include('pages.staff-ai-agents.partials.scope-select', [
                'name' => 'scope_location_ids',
                'label' => 'Locations',
                'hint' => '(empty = all)',
                'options' => $formOptions['locations']->pluck('name', 'id'),
                'selected' => old('scope_location_ids', []),
                'placeholder' => 'Search locations…',
            ])
        @endif

        <div class="flex gap-2 pt-2">
            <x-ui.btn type="submit" variant="primary">Create agent</x-ui.btn>
            <x-ui.btn href="{{ route('staff.index') }}" variant="outline">Cancel</x-ui.btn>
        </div>
    </form>
</div>
@endsection
