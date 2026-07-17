
@props([
    'progress' => 0,
    'size' => 'sm',
    'label' => 'none',
    'className' => ''
])

@php
    $sizeClasses = [
        'sm' => 'h-2',
        'md' => 'h-3',
        'lg' => 'h-4',
        'xl' => 'h-5',
    ];
    
    $baseClasses = 'relative w-full bg-gray-200 rounded-full dark:bg-gray-800';
    $progressClasses = 'absolute left-0 h-full bg-blue-500 rounded-full';
    $sizeClass = $sizeClasses[$size] ?? $sizeClasses['sm'];
@endphp

<div class="flex items-center {{ $className }}">
    <div class="{{ $baseClasses }} {{ $sizeClass }}">
        <div
            class="{{ $progressClasses }} {{ $label === 'inside' ? 'flex items-center justify-center' : '' }}"
            style="width: {{ $progress }}%"
        >
            @if($label === 'inside')
                <span class="absolute inset-0 flex items-center justify-center text-white font-medium text-[10px] leading-tight">
                    {{ $progress }}%
                </span>
            @endif
        </div>
    </div>
    
    @if($label === 'outside')
        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-400">
            {{ $progress }}%
        </span>
    @endif
</div>