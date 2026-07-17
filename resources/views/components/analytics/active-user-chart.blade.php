<div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex items-start justify-between">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
            Active Users
        </h3>

        <!-- Dropdown Menu -->
        <x-common.dropdown-menu />
        <!-- End Dropdown Menu -->

    </div>

    <div class="mt-6 flex items-end gap-1.5">
        <div class="flex items-center gap-2.5">
            <span class="relative inline-block w-5 h-5">
                <span
                    class="absolute w-2 h-2 transform -translate-x-1/2 -translate-y-1/2 rounded-full bg-error-500 top-1/2 left-1/2">
                    <span
                        class="absolute inline-flex w-4 h-4 rounded-full opacity-75 bg-error-400 -top-1 -left-1 animate-ping">
                    </span>
                </span>
            </span>
            <span class="font-semibold text-gray-800 activeUsers text-title-sm dark:text-white/90">
                364
            </span>
        </div>
        <span class="block mb-1 text-gray-500 text-theme-sm dark:text-gray-400">
            Live visitors
        </span>
    </div>

    <div class="my-5 min-h-[155px] rounded-xl bg-gray-50 dark:bg-gray-900">
        <div id="chartFive" class="-mr-2.5 -ml-[22px] h-full"></div>
    </div>

    <div class="flex items-center justify-center gap-6">
        <div>
            <p class="text-lg font-semibold text-center text-gray-800 dark:text-white/90">
                224
            </p>
            <p class="text-theme-xs mt-0.5 text-center text-gray-500 dark:text-gray-400">
                Avg, Daily
            </p>
        </div>

        <div class="w-px bg-gray-200 h-11 dark:bg-gray-800"></div>

        <div>
            <p class="text-lg font-semibold text-center text-gray-800 dark:text-white/90">
                1.4K
            </p>
            <p class="text-theme-xs mt-0.5 text-center text-gray-500 dark:text-gray-400">
                Avg, Weekly
            </p>
        </div>

        <div class="w-px bg-gray-200 h-11 dark:bg-gray-800"></div>

        <div>
            <p class="text-lg font-semibold text-center text-gray-800 dark:text-white/90">
                22.1K
            </p>
            <p class="text-theme-xs mt-0.5 text-center text-gray-500 dark:text-gray-400">
                Avg, Monthly
            </p>
        </div>
    </div>
</div>
