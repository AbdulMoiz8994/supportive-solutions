@props(['image' => null, 'title' => null, 'description' => null])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]']) }}>
    @if($image)
        <div class="mb-5 overflow-hidden rounded-lg">
            <img src="{{ $image }}" alt="{{ $title ?? 'card' }}" class="overflow-hidden rounded-lg w-full" />
        </div>
    @endif

    <div>
        @if($title)
            <h4 class="mb-1 font-medium text-gray-800 text-theme-xl dark:text-white/90">
                {{ $title }}
            </h4>
        @endif

        @if($description)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $description }}
            </p>
        @endif

        {{ $slot }}
    </div>
</div>
