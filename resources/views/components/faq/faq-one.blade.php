@php
    $faqs = [
        [
            'question' => 'Do I get free updates?',
            'answer' =>
                'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec quis magna ac nibh malesuada consectetur at vitae ipsum orem ipsum dolor sit amet, consectetur adipiscing elit nam fermentum, leo et lacinia accumsan.',
        ],
        [
            'question' => 'Do I get free updates?',
            'answer' =>
                'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec quis magna ac nibh malesuada consectetur at vitae ipsum orem ipsum dolor sit amet, consectetur adipiscing elit nam fermentum, leo et lacinia accumsan.',
        ],
        [
            'question' => 'Can I Customize TailAdmin to suit my needs?',
            'answer' =>
                'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec quis magna ac nibh malesuada consectetur at vitae ipsum orem ipsum dolor sit amet, consectetur adipiscing elit nam fermentum, leo et lacinia accumsan.',
        ],
        [
            'question' => 'What does "Unlimited Projects" mean?',
            'answer' =>
                'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec quis magna ac nibh malesuada consectetur at vitae ipsum orem ipsum dolor sit amet, consectetur adipiscing elit nam fermentum, leo et lacinia accumsan.',
        ],
    ];
@endphp

<div x-data="faq()" class="space-y-4">
    @foreach ($faqs as $index => $faq)
        <div
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
            x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }"
            @click.away="open = false"
        >
            <!-- Header -->
            <div
                @click="open = !open"
                class="flex items-center justify-between py-3 pl-6 pr-3 cursor-pointer"
                :class="open ? 'bg-gray-50 dark:bg-white/[0.03]' : ''"
            >
                <h4 class="text-lg font-medium text-gray-800 dark:text-white/90">
                    {{ $faq['question'] }}
                </h4>

                <button
                    :class="open 
                        ? 'text-gray-800 dark:text-white/90 rotate-180' 
                        : 'text-gray-500 dark:text-gray-400'"
                    class="flex h-12 w-full max-w-12 items-center justify-center rounded-full bg-gray-100 duration-200 ease-linear dark:bg-white/[0.03] transition-transform"
                >
                    <svg class="stroke-current" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5.75 8.875L12 15.125L18.25 8.875" stroke="" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 transform -translate-y-2"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 transform translate-y-0"
                x-transition:leave-end="opacity-0 transform -translate-y-2"
                class="px-6 py-7"
            >
                <p class="text-base text-gray-500 dark:text-gray-400">
                    {{ $faq['answer'] }}
                </p>
            </div>
        </div>
    @endforeach
</div>

<script>
function faq() {
    return {
      
    }
}
</script>