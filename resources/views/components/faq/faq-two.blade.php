@php
    $faqItems = [
        [
            'question' => 'Do I get free updates?',
            'answer' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec quis magna ac nibh malesuada consectetur at vitae ipsum orem ipsum dolor sit amet, consectetur adipiscing elit nam fermentum, leo et lacinia accumsan.',
        ],
        [
            'question' => 'Which license type is suitable for me?',
            'answer' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec quis magna ac nibh malesuada consectetur at vitae ipsum orem ipsum dolor sit amet, consectetur adipiscing elit nam fermentum, leo et lacinia accumsan.',
        ],
        [
            'question' => 'What are the "Seats" mentioned on pricing plans?',
            'answer' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec quis magna ac nibh malesuada consectetur at vitae ipsum orem ipsum dolor sit amet, consectetur adipiscing elit nam fermentum, leo et lacinia accumsan.',
        ],
        [
            'question' => 'Can I Customize TailAdmin to suit my needs?',
            'answer' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec quis magna ac nibh malesuada consectetur at vitae ipsum orem ipsum dolor sit amet, consectetur adipiscing elit nam fermentum, leo et lacinia accumsan.',
        ],
        [
            'question' => 'What does "Unlimited Projects" mean?',
            'answer' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec quis magna ac nibh malesuada consectetur at vitae ipsum orem ipsum dolor sit amet, consectetur adipiscing elit nam fermentum, leo et lacinia accumsan.',
        ],
        [
            'question' => 'Can I upgrade to a higher plan?',
            'answer' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec quis magna ac nibh malesuada consectetur at vitae ipsum orem ipsum dolor sit amet, consectetur adipiscing elit nam fermentum, leo et lacinia accumsan.',
        ],
        [
            'question' => 'Are there dark and light mode options?',
            'answer' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec quis magna ac nibh malesuada consectetur at vitae ipsum orem ipsum dolor sit amet, consectetur adipiscing elit nam fermentum, leo et lacinia accumsan.',
        ],
    ];

    $firstColumn = array_slice($faqItems, 0, 3);
    $secondColumn = array_slice($faqItems, 3);
@endphp

<div class="grid grid-cols-1 gap-x-8 xl:grid-cols-2">
    <!-- First Column -->
    <div class="space-y-3">
        @foreach ($firstColumn as $index => $item)
            <div x-data="{ isOpen: {{ $loop->first ? 'true' : 'false' }} }" @click.away="open = false" class="overflow-hidden rounded-xl">
                <div
                    :class="isOpen ? 'bg-brand-50 dark:bg-brand-100' : 'bg-gray-100 dark:bg-white/[0.03]'"
                    class="transition-colors"
                >
                    <div @click="isOpen = !isOpen" class="flex items-center justify-between px-6 py-4 cursor-pointer">
                        <h4
                            :class="isOpen ? 'text-gray-800' : 'text-gray-800 dark:text-white/90'"
                            class="text-lg font-medium"
                        >
                            {{ $item['question'] }}
                        </h4>

                        <button
                            :class="isOpen ? 'text-gray-800 dark:text-gray-800' : 'text-gray-500 dark:text-gray-400'"
                            class="transition-colors"
                        >
                            <!-- Plus Icon (when closed) -->
                            <span x-show="!isOpen">
                                <svg class="fill-current" width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M7 12.9998C6.44772 12.9998 6 13.4475 6 13.9998C6 14.5521 6.44772 14.9998 7 14.9998V12.9998ZM21.0008 14.9998C21.5531 14.9998 22.0008 14.5521 22.0008 13.9998C22.0008 13.4475 21.5531 12.9998 21.0008 12.9998V14.9998ZM15.0003 6.99951C15.0003 6.44723 14.5526 5.99951 14.0003 5.99951C13.448 5.99951 13.0003 6.44723 13.0003 6.99951H15.0003ZM13.0003 21.0003C13.0003 21.5526 13.448 22.0003 14.0003 22.0003C14.5526 22.0003 15.0003 21.5526 15.0003 21.0003H13.0003ZM7 14.9998H21.0008V12.9998H7V14.9998ZM13.0003 6.99951V21.0003H15.0003V6.99951H13.0003Z" fill="" />
                                </svg>
                            </span>

                            <!-- Minus Icon (when open) -->
                            <span x-show="isOpen" x-cloak>
                                <svg class="fill-current" width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M7 12.9995C6.44772 12.9995 6 13.4472 6 13.9995C6 14.5518 6.44772 14.9995 7 14.9995V12.9995ZM21.0008 14.9995C21.5531 14.9995 22.0008 14.5518 22.0008 13.9995C22.0008 13.4472 21.5531 12.9995 21.0008 12.9995V14.9995ZM7 14.9995H21.0008V12.9995H7V14.9995Z" fill="" />
                                </svg>
                            </span>
                        </button>
                    </div>

                    <div
                        x-show="isOpen"
                        class="p-6 border-t border-brand-100 dark:border-brand-200"
                    >
                        <p class="text-base text-gray-800">
                            {{ $item['answer'] }}
                        </p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Second Column -->
    <div class="space-y-3">
        @foreach ($secondColumn as $index => $item)
            <div x-data="{ isOpen: false }" @click.away="isOpen = false" class="overflow-hidden rounded-xl">
                <div
                    :class="isOpen ? 'bg-brand-50 dark:bg-brand-100' : 'bg-gray-100 dark:bg-white/[0.03]'"
                    class="transition-colors"
                >
                    <div @click="isOpen = !isOpen" class="flex items-center justify-between px-6 py-4 cursor-pointer">
                        <h4
                            :class="isOpen ? 'text-gray-800' : 'text-gray-800 dark:text-white/90'"
                            class="text-lg font-medium"
                        >
                            {{ $item['question'] }}
                        </h4>

                        <button
                            :class="isOpen ? 'text-gray-800 dark:text-gray-800' : 'text-gray-500 dark:text-gray-400'"
                            class="transition-colors"
                        >
                            <!-- Plus Icon (when closed) -->
                            <span x-show="!isOpen">
                                <svg class="fill-current" width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M7 12.9998C6.44772 12.9998 6 13.4475 6 13.9998C6 14.5521 6.44772 14.9998 7 14.9998V12.9998ZM21.0008 14.9998C21.5531 14.9998 22.0008 14.5521 22.0008 13.9998C22.0008 13.4475 21.5531 12.9998 21.0008 12.9998V14.9998ZM15.0003 6.99951C15.0003 6.44723 14.5526 5.99951 14.0003 5.99951C13.448 5.99951 13.0003 6.44723 13.0003 6.99951H15.0003ZM13.0003 21.0003C13.0003 21.5526 13.448 22.0003 14.0003 22.0003C14.5526 22.0003 15.0003 21.5526 15.0003 21.0003H13.0003ZM7 14.9998H21.0008V12.9998H7V14.9998ZM13.0003 6.99951V21.0003H15.0003V6.99951H13.0003Z" fill="" />
                                </svg>
                            </span>

                            <!-- Minus Icon (when open) -->
                            <span x-show="isOpen" x-cloak>
                                <svg class="fill-current" width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M7 12.9995C6.44772 12.9995 6 13.4472 6 13.9995C6 14.5518 6.44772 14.9995 7 14.9995V12.9995ZM21.0008 14.9995C21.5531 14.9995 22.0008 14.5518 22.0008 13.9995C22.0008 13.4472 21.5531 12.9995 21.0008 12.9995V14.9995ZM7 14.9995H21.0008V12.9995H7V14.9995Z" fill="" />
                                </svg>
                            </span>
                        </button>
                    </div>

                    <div
                        x-show="isOpen"
                        class="p-6 border-t border-brand-100 dark:border-brand-200"
                    >
                        <p class="text-base text-gray-800">
                            {{ $item['answer'] }}
                        </p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
