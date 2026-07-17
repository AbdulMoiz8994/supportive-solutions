@props(['sources' => []])

<div class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex items-center justify-between mb-5">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
            Referral Source Tracking
        </h3>

        <!-- Dropdown Menu -->
        <x-common.dropdown-menu />
    </div>
    <div class="flex flex-col items-center gap-8 xl:flex-row">
        <div id="chartTwelve" class="chartDarkStyle"></div>
        <div class="flex flex-col items-start gap-6 sm:flex-row xl:flex-col">
            @forelse($sources as $source)
                <div class="flex items-start gap-2.5">
                    <div class="bg-brand-500 mt-1.5 h-2 w-2 rounded-full"></div>
                    <div>
                        <h5 class="mb-1 font-medium text-gray-800 text-theme-sm dark:text-white/90">
                            {{ $source['label'] }}
                        </h5>
                        <div class="flex items-center gap-2">
                            <p class="font-medium text-gray-700 text-theme-sm dark:text-gray-400">
                                {{ $source['count'] }} Referrals
                            </p>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400 italic">No referral data tracked yet.</p>
            @endforelse
        </div>
    </div>
</div>
