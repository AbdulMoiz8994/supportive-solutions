@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <nav aria-label="Breadcrumb" class="text-[12px] font-semibold text-[#2563eb]">
        <a href="{{ route('directory', session('directory.filters', [])) }}" class="hover:text-[#1d4ed8]">‹ Directories</a>
        <span class="text-[#94a3b8]"> / </span><span class="text-[#64748b]">Add entry</span>
    </nav>

    @include('pages.directory._alerts')

    <div>
        <h1 class="text-[28px] font-extrabold leading-tight tracking-tight text-[#0f172a]">Add entry</h1>
        <p class="mt-1.5 text-[13px] text-[#64748b]">Register a physician, coordinator, vendor, or partner contact.</p>
    </div>

    <form id="directory-contact-form" action="{{ route('directory.store') }}" method="POST" x-data="{ submitting: false }" x-on:submit="submitting = true" class="space-y-6">
        @csrf
        @include('pages.directory._form', ['types' => $types])
        @include('pages.directory._form-actions', ['submitLabel' => 'Save entry', 'cancelUrl' => route('directory', session('directory.filters', []))])
    </form>
</div>
@endsection
