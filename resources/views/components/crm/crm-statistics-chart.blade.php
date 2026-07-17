<div
    class="rounded-2xl border border-gray-200 bg-white px-5 pb-5 pt-5 dark:border-gray-800 dark:bg-white/[0.03] sm:px-6 sm:pt-6">
    <div class="flex flex-col gap-5 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                Statistics
            </h3>
            <p class="mt-1 text-gray-500 text-theme-sm dark:text-gray-400">
                Target you’ve set for each month
            </p>
        </div>

        <div x-data="{ selected: 'optionOne' }"
            class="inline-flex items-center gap-0.5 rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900">
            <button @click="selected = 'optionOne'"
                :class="selected === 'optionOne' ? 'shadow-theme-xs text-gray-900 dark:text-white bg-white dark:bg-gray-800' :
                    'text-gray-500 dark:text-gray-400'"
                class="px-3 py-2 font-medium rounded-md text-theme-sm hover:text-gray-900 hover:shadow-theme-xs dark:hover:bg-gray-800 dark:hover:text-white">
                Monthly
            </button>

            <button @click="selected = 'optionTwo'"
                :class="selected === 'optionTwo' ? 'shadow-theme-xs text-gray-900 dark:text-white bg-white dark:bg-gray-800' :
                    'text-gray-500 dark:text-gray-400'"
                class="px-3 py-2 font-medium rounded-md text-theme-sm hover:text-gray-900 hover:shadow-theme-xs dark:hover:text-white">
                Quarterly
            </button>

            <button @click="selected = 'optionThree'"
                :class="selected === 'optionThree' ? 'shadow-theme-xs text-gray-900 dark:text-white bg-white dark:bg-gray-800' :
                    'text-gray-500 dark:text-gray-400'"
                class="px-3 py-2 font-medium rounded-md text-theme-sm hover:text-gray-900 hover:shadow-theme-xs dark:hover:text-white">
                Annually
            </button>
        </div>
    </div>

    <div class="flex gap-4 sm:gap-9">
        <div class="flex items-start gap-2">
            <div>
                <h4 class="mb-0.5 text-base font-bold text-gray-800 dark:text-white/90 sm:text-theme-xl">
                    ${{ number_format($monthlyBilling ?? 0, 2) }}
                </h4>
                <span class="text-gray-500 text-theme-xs dark:text-gray-400">
                    Gross Monthly Revenue
                </span>
            </div>

            <span
                class="mt-1.5 flex items-center gap-1 rounded-full bg-success-50 px-2 py-0.5 text-theme-xs font-medium text-success-600 dark:bg-success-500/15 dark:text-success-500">
                +12%
            </span>
        </div>

        <div class="flex items-start gap-2">
            <div>
                <h4 class="mb-0.5 text-base font-bold text-gray-800 dark:text-white/90 sm:text-theme-xl">
                    ${{ number_format(($monthlyBilling ?? 0) * 0.45, 2) }}
                </h4>
                <span class="text-gray-500 text-theme-xs dark:text-gray-400">
                    Est. Agency Profit (45%)
                </span>
            </div>

            <span
                class="mt-1.5 flex items-center gap-1 rounded-full bg-success-50 px-2 py-0.5 text-theme-xs font-medium text-success-600 dark:bg-success-500/15 dark:text-success-500">
                Active
            </span>
        </div>
    </div>
    <div class="max-w-full overflow-x-auto custom-scrollbar">
        <div id="chartTen" class="-ml-4 min-w-[1000px] pl-2 xl:min-w-full"></div>
    </div>
</div>
