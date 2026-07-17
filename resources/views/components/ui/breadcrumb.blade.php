@props([
    'items' => [],
    'variant' => 'default', // default, withIcon, dotted, chevron
])

@php
    $gapClass = $variant === 'dotted' ? 'gap-2' : 'gap-1.5';
@endphp

<nav {{ $attributes }}>
    <ol class="flex flex-wrap items-center {{ $gapClass }}">
        @foreach ($items as $index => $item)
            <li class="flex items-center gap-1.5">
                {{-- Separator --}}
                @if ($index > 0)
                    <span class="text-gray-500 dark:text-gray-400">
                        @switch($variant)
                            @case('withIcon')
                            @case('default')
                                <span>/</span>
                                @break

                            @case('dotted')
                                <span class="block w-1 h-1 bg-gray-400 rounded-full"></span>
                                @break

                            @case('chevron')
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                                @break
                        @endswitch
                    </span>
                @endif

                {{-- Breadcrumb Item --}}
                @if (!empty($item['href']))
                    <a
                        href="{{ $item['href'] }}"
                        class="flex items-center gap-1 text-sm text-gray-500 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400"
                    >
                        @if ($index === 0 && $variant === 'withIcon')
                            <svg class="fill-current" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                             <path fill-rule="evenodd" clip-rule="evenodd" d="M7.48994 3.61404C7.79216 3.38738 8.20771 3.38738 8.50993 3.61404L12.3433 6.48904C12.5573 6.64957 12.6833 6.9015 12.6833 7.16904V11.8333C12.6833 12.3028 12.3027 12.6833 11.8333 12.6833H8.64993V10.8333C8.64993 10.4744 8.35892 10.1833 7.99993 10.1833C7.64095 10.1833 7.34993 10.4744 7.34993 10.8333V12.6833H4.1666C3.69716 12.6833 3.3166 12.3028 3.3166 11.8333V7.16904C3.3166 6.9015 3.44257 6.64957 3.6566 6.48904L7.48994 3.61404ZM7.99478 13.9833H4.1666C2.97919 13.9833 2.0166 13.0207 2.0166 11.8333V7.16904C2.0166 6.49231 2.33522 5.85508 2.8766 5.44904L6.70994 2.57404C7.47438 2.00071 8.52549 2.00071 9.28993 2.57404L13.1233 5.44904C13.6647 5.85508 13.9833 6.49232 13.9833 7.16904V11.8333C13.9833 13.0207 13.0207 13.9833 11.8333 13.9833H8.00509C8.00337 13.9833 8.00166 13.9833 7.99993 13.9833C7.99821 13.9833 7.9965 13.9833 7.99478 13.9833Z" fill=""></path>
                            </svg>
                        @endif
                        {{ $item['label'] }}
                    </a>
                @else
                    <span class="flex items-center gap-1 text-sm text-gray-800 dark:text-white/90">
                        @if ($index === 0 && $variant === 'withIcon')
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                        @endif
                        {{ $item['label'] }}
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
