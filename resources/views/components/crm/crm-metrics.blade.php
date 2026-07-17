@props(['metrics' => []])

@php
    $defaultMetrics = [
        [
            'value' => '$120,369',
            'label' => 'Active Deal',
            'change' => '+20%',
            'changeType' => 'success',
            'comparison' => 'From last month',
        ],
        [
            'value' => '$234,210',
            'label' => 'Revenue Total',
            'change' => '+9.0%',
            'changeType' => 'success',
            'comparison' => 'From last month',
        ],
        [
            'value' => '874',
            'label' => 'Closed Deals',
            'change' => '-4.5%',
            'changeType' => 'error',
            'comparison' => 'From last month',
        ],
    ];

    $metricsList = !empty($metrics) ? $metrics : $defaultMetrics;

    // Helper function for change badge classes
    $getChangeClasses = function ($type) {
        $baseClasses = 'flex items-center gap-1 rounded-full px-2 py-0.5 text-theme-xs font-medium';

        return match ($type) {
            'success' => $baseClasses . ' bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500',
            'error' => $baseClasses . ' bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500',
            'warning' => $baseClasses . ' bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-orange-400',
            default => $baseClasses . ' bg-gray-50 text-gray-600 dark:bg-gray-500/15 dark:text-gray-400',
        };
    };
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-3">
    @foreach ($metricsList as $metric)
        <!-- Metric Item Start -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <h4 class="text-title-sm font-bold text-gray-800 dark:text-white/90">
                {{ $metric['value'] }}
            </h4>

            <div class="mt-4 flex items-end justify-between sm:mt-5">
                <div>
                    <p class="text-theme-sm text-gray-700 dark:text-gray-400">
                        {{ $metric['label'] }}
                    </p>
                </div>

                <div class="flex items-center gap-1">
                    <span class="{{ $getChangeClasses($metric['changeType']) }}">
                        {{ $metric['change'] }}
                    </span>

                    <span class="text-theme-xs text-gray-500 dark:text-gray-400">
                        {{ $metric['comparison'] }}
                    </span>
                </div>
            </div>
        </div>
        <!-- Metric Item End -->
    @endforeach
</div>
