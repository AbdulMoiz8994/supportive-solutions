@props([
    'type' => 'submit',
    'class' => '',
    'variant' => 'primary', // 'primary', 'secondary', 'danger', 'success'
    'label' => 'Submit',
    'icon' => null,
])

@php
    $baseClasses = "px-6 py-2.5 text-sm font-bold rounded-xl transition-all flex items-center justify-center gap-2 transform active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed uppercase tracking-widest";
    
    $variants = [
        'primary' => 'bg-brand-500 text-white hover:bg-brand-600 shadow-xl shadow-brand-500/20',
        'secondary' => 'bg-gray-100 text-gray-700 hover:bg-gray-200',
        'danger' => 'bg-red-500 text-white hover:bg-red-600 shadow-xl shadow-red-500/20',
        'success' => 'bg-green-600 text-white hover:bg-green-700 shadow-xl shadow-green-600/20',
    ];
    
    $variantClass = $variants[$variant] ?? $variants['primary'];
@endphp

<button 
    type="{{ $type }}" 
    x-data="{ loading: false }" 
    @click="if('{{ $type }}' === 'submit') { loading = true; $el.closest('form')?.submit() }"
    :disabled="loading"
    {{ $attributes->merge(['class' => "$baseClasses $variantClass $class"]) }}
>
    <!-- Spinner SVG -->
    <svg x-show="loading" class="animate-spin h-4 w-4 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" x-cloak>
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>

    <!-- Content -->
    <span x-show="!loading" class="flex items-center gap-2">
        @if($icon) {!! $icon !!} @endif
        {{ $slot->isEmpty() ? $label : $slot }}
    </span>
    
    <!-- Loading Text (Optional) -->
    <span x-show="loading" x-cloak>Processing...</span>
</button>
