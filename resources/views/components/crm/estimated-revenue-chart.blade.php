<div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
    <div class="flex justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                Monthly Billing Analytics
            </h3>
            <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                Actual vs Monthly Authorized Target
            </p>
        </div>

        <x-common.dropdown-menu />
    </div>

    <div class="relative">
        <div id="chartEleven"></div>
        <span
            class="absolute left-1/2 top-[60%] -translate-x-1/2 -translate-y-[60%] text-xs font-bold text-gray-800 dark:text-white/90">
            ${{ number_format($monthlyBilling ?? 0, 0) }}
        </span>
    </div>

    <div class="border-gary-200 mt-6 space-y-5 border-t pt-6 dark:border-gray-800">
        <div>
            <p class="mb-2 text-theme-sm text-gray-500 dark:text-gray-400">
                Monthly Realized Revenue
            </p>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div>
                        <p class="text-base font-semibold text-gray-800 dark:text-white/90">
                            ${{ number_format($monthlyBilling ?? 0, 2) }}
                        </p>
                    </div>
                </div>

                <div class="flex w-full max-w-[140px] items-center gap-3">
                    <div class="relative block h-2 w-full max-w-[100px] rounded-sm bg-gray-200 dark:bg-gray-800">
                        <div
                            class="absolute left-0 top-0 flex h-full items-center justify-center rounded-sm bg-brand-500 text-xs font-medium text-white"
                            style="width: {{ min(100, (($monthlyBilling ?? 0) / ($revenueGoal ?? 10000)) * 100) }}%">
                        </div>
                    </div>
                    <p class="text-theme-sm font-medium text-gray-700 dark:text-gray-400">
                        {{ round(min(100, (($monthlyBilling ?? 0) / ($revenueGoal ?? 10000)) * 100)) }}%
                    </p>
                </div>
            </div>
        </div>

        <div>
            <p class="mb-2 text-theme-sm text-gray-500 dark:text-gray-400">Enrolled Clients</p>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div>
                        <p class="text-base font-semibold text-gray-800 dark:text-white/90">
                            {{ $clientCount ?? 0 }} Clients
                        </p>
                    </div>
                </div>

                <div class="flex w-full max-w-[140px] items-center gap-3">
                    <div class="relative block h-2 w-full max-w-[100px] rounded-sm bg-gray-200 dark:bg-gray-800">
                        <div
                            class="absolute left-0 top-0 flex h-full items-center justify-center rounded-sm bg-brand-500 text-xs font-medium text-white"
                            style="width: {{ min(100, (($clientCount ?? 0) / 100) * 100) }}%">
                        </div>
                    </div>
                    <p class="text-theme-sm font-medium text-gray-700 dark:text-gray-400">
                        {{ min(100, $clientCount ?? 0) }}%
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
