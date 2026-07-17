@props([
    'name',
    'label',
    'sortable' => true,
    'class' => '',
])

@php
    $currentSort = request('sort_by');
    $currentOrder = request('order', 'asc');
    $isSorted = $currentSort === $name;
    $nextOrder = ($isSorted && $currentOrder === 'asc') ? 'desc' : 'asc';
    
    // Build sorting URL
    $params = request()->all();
    $params['sort_by'] = $name;
    $params['order'] = $nextOrder;
    $url = request()->url() . '?' . http_build_query($params);
@endphp

<th {{ $attributes->merge(['class' => 'pb-3 text-xs font-bold text-gray-400 uppercase tracking-widest ' . $class]) }}>
    @if($sortable)
        <a href="{{ $url }}" class="flex items-center gap-1 hover:text-brand-500 transition-colors group">
            {{ $label }}
            <div class="flex flex-col -space-y-1">
                <svg class="w-2.5 h-2.5 {{ $isSorted && $currentOrder === 'asc' ? 'text-brand-500' : 'text-gray-300 group-hover:text-gray-400' }}" fill="currentColor" viewBox="0 0 24 24"><path d="M7 14l5-5 5 5H7z"/></svg>
                <svg class="w-2.5 h-2.5 {{ $isSorted && $currentOrder === 'desc' ? 'text-brand-500' : 'text-gray-300 group-hover:text-gray-400' }}" fill="currentColor" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg>
            </div>
        </a>
    @else
        {{ $label }}
    @endif
</th>
