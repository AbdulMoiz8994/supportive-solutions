<div
    class="flex flex-col justify-between space-y-6 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
    <div class="flex items-start justify-between">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Total revenue earned</p>
            <h3 class="text-3xl font-medium text-gray-800 dark:text-white/90">$23,445,700</h3>
        </div>

        <div class="relative">
            <!-- Dropdown Menu -->
            <x-common.dropdown-menu />
            <!-- End Dropdown Menu -->
        </div>
    </div>

    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Shipped quantities</p>
            <h3 class="text-3xl font-medium text-gray-800 dark:text-white/90">9,258</h3>
        </div>

        <div class="h-[60px] w-full max-w-[150px]">
            <div id="chartTwentyOne"></div>
        </div>
    </div>
</div>
