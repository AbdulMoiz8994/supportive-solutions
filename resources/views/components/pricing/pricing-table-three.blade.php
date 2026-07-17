@php
    $plans = [
        [
            'name' => 'Personal',
            'tagline' => 'Perfect plan for Starters',
            'price' => 'Free',
            'period' => 'For a Lifetime',
            'button_text' => 'Current Plan',
            'button_class' =>
                'border border-gray-300 bg-white text-gray-400 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-white/[0.03] dark:hover:text-gray-200',
            'disabled' => true,
            'bg_class' => 'bg-white dark:bg-white/[0.03]',
            'text_class' => 'text-gray-800 dark:text-white/90',
            'price_class' => 'text-gray-800 dark:text-white/90',
            'features' => ['Unlimited Projects', 'Share with 5 team members', 'Sync across devices'],
        ],
        [
            'name' => 'Professional',
            'tagline' => 'For users who want to do more',
            'price' => '$99.00',
            'period' => '/year',
            'button_text' => 'Try for Free',
            'button_class' => 'bg-brand-500 text-white hover:bg-brand-600',
            'bg_class' => 'bg-white dark:bg-white/[0.03]',
            'text_class' => 'text-gray-800 dark:text-white/90',
            'price_class' => 'text-gray-800 dark:text-white/90',
            'features' => [
                'Unlimited Projects',
                'Share with 5 team members',
                'Sync across devices',
                '30 days version history',
            ],
        ],
        [
            'name' => 'Team',
            'tagline' => 'Your entire team in one place',
            'price' => '$299',
            'period' => '/year',
            'button_text' => 'Try for Free',
            'button_class' => 'bg-white text-gray-800 hover:bg-gray-50',
            'bg_class' => 'bg-brand-500',
            'text_class' => 'text-white',
            'price_class' => 'text-white',
            'recommended' => true,
            'features' => [
                'Unlimited Projects',
                'Share with 5 team members',
                'Sync across devices',
                'Sharing permissions',
                'Admin tools',
            ],
        ],
        [
            'name' => 'Enterprise',
            'tagline' => 'Run your company on your terms',
            'price' => 'Custom',
            'period' => 'Reach out for a quote',
            'button_text' => 'Try for Free',
            'button_class' => 'bg-brand-500 text-white hover:bg-brand-600',
            'bg_class' => 'bg-white dark:bg-white/[0.03]',
            'text_class' => 'text-gray-800 dark:text-white/90',
            'price_class' => 'text-gray-800 dark:text-white/90',
            'features' => [
                'Unlimited Projects',
                'Share with 5 team members',
                'Sync across devices',
                'Sharing permissions',
                'User provisioning (SCIM)',
                'Advanced security',
            ],
        ],
    ];
@endphp

<div class="grid gap-5 grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 xl:gap-3 2xl:grid-cols-4">
    @foreach ($plans as $plan)
        <div>
            <div class="relative rounded-2xl {{ $plan['bg_class'] }} p-6 z-10">
                @if (isset($plan['recommended']))
                    <div class="absolute px-3 py-1 font-medium text-white rounded-lg right-4 top-4 -z-10 bg-white/10 text-theme-xs">
                        Recommended
                    </div>
                @endif

                <span class="block font-semibold {{ $plan['text_class'] }} text-theme-xl">
                    {{ $plan['name'] }}
                </span>

                <p class="mt-1 text-sm {{ $plan['text_class'] }}">
                    {{ $plan['tagline'] }}
                </p>

                <h2 class="mb-0.5 mt-4 text-title-sm font-bold {{ $plan['price_class'] }}">
                    {{ $plan['price'] }}
                </h2>

                <span class="inline-block mb-6 text-sm {{ $plan['text_class'] }}">
                    {{ $plan['period'] }}
                </span>

                <button
                    @if(isset($plan['disabled'])) disabled @endif
                    class="flex h-11 w-full items-center justify-center rounded-lg {{ $plan['button_class'] }} p-3.5 text-sm font-medium shadow-theme-xs transition-colors
                        @if(isset($plan['disabled'])) disabled:pointer-events-none @endif"
                >
                    {{ $plan['button_text'] }}
                </button>

                <ul class="mt-6 space-y-3">
                    @foreach ($plan['features'] as $feature)
                        <li class="flex items-center gap-3 text-sm {{ $plan['text_class'] }}">
                            <svg class="text-{{ isset($plan['recommended']) ? 'white' : 'success-500' }}" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M13.4017 4.35986L6.12166 11.6399L2.59833 8.11657" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                            {{ $feature }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endforeach
</div>
