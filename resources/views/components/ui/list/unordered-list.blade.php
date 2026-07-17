@props([
    'items' => [
        'Lorem ipsum dolor sit amet',
        'It is a long established fact reader',
        'Lorem ipsum dolor sit amet',
        'Lorem ipsum dolor sit amet',
        'Lorem ipsum dolor sit amet',
    ],
])

<div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] sm:w-fit">
    <ul class="flex flex-col">
        @foreach ($items as $item)
            <li
                class="flex items-center gap-2 border-b border-gray-200 px-3 py-2.5 text-sm text-gray-500 last:border-b-0 dark:border-gray-800 dark:text-gray-400"
            >
                <span class="ml-2 block h-[3px] w-[3px] rounded-full bg-gray-500 dark:bg-gray-400"></span>
                <span>{{ $item }}</span>
            </li>
        @endforeach
    </ul>
</div>
