@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <nav aria-label="Breadcrumb" class="text-[12px] font-semibold text-[#2563eb]">
        <a href="{{ route('directory', session('directory.filters', [])) }}" class="hover:text-[#1d4ed8]">‹ Directories</a>
        <span class="text-[#94a3b8]"> / </span>
        <a href="{{ route('directory.show', $contact->id) }}" class="hover:text-[#1d4ed8]">{{ $contact->name }}</a>
        <span class="text-[#94a3b8]"> / </span><span class="text-[#64748b]">Edit</span>
    </nav>

    @include('pages.directory._alerts')

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-[28px] font-extrabold leading-tight tracking-tight text-[#0f172a]">Edit entry</h1>
            <p class="mt-1.5 text-[13px] text-[#64748b]">Update details for {{ $contact->name }}.</p>
        </div>
        <div class="rounded-xl border border-[#e2e8f0] bg-[#fafcff] px-4 py-3 text-[11px] text-[#64748b]">
            <div class="flex justify-between gap-6"><span>Created</span><span class="font-semibold text-[#334155]">{{ $contact->created_at?->format('M j, Y') }}</span></div>
            <div class="mt-1 flex justify-between gap-6"><span>Last updated</span><span class="font-semibold text-[#334155]">{{ $contact->updated_at?->format('M j, Y g:i A') }}</span></div>
            @if ($createdBy ?? null)
                <div class="mt-1 flex justify-between gap-6"><span>Created by</span><span class="font-semibold text-[#334155]">{{ $createdBy->name }}</span></div>
            @endif
        </div>
    </div>

    <form id="directory-contact-form" action="{{ route('directory.update', $contact->id) }}" method="POST" x-data="{ submitting: false }" x-on:submit="submitting = true" class="space-y-6">
        @csrf
        @method('PUT')
        @include('pages.directory._form', ['contact' => $contact, 'types' => $types])
        @include('pages.directory._form-actions', ['submitLabel' => 'Save changes', 'cancelUrl' => route('directory.show', $contact->id)])
    </form>
</div>
@endsection
