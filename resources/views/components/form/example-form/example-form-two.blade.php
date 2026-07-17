<x-common.component-card title="Example Form">
    <form>
        <div class="-mx-2.5 flex flex-wrap gap-y-5">
            <div class="w-full px-2.5">
                <h4
                    class="border-b border-gray-200 pb-4 text-base font-medium text-gray-800 dark:border-gray-800 dark:text-white/90">
                    Personal Info
                </h4>
            </div>

            <div class="w-full px-2.5 xl:w-1/2">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    First Name
                </label>
                <input type="text" placeholder="Enter first name"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
            </div>

            <div class="w-full px-2.5 xl:w-1/2">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Last Name
                </label>
                <input type="text" placeholder="Enter last name"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
            </div>

            <div class="w-full px-2.5">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Gender
                </label>
                <div x-data="{ isOptionSelected: false }" class="relative z-20 bg-transparent">
                    <select
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-3 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        :class="isOptionSelected && 'text-gray-500 dark:text-gray-400'"
                        @change="isOptionSelected = true">
                        <option value="" class="text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                            Male
                        </option>
                        <option value="" class="text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                            Female
                        </option>
                        <option value="" class="text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                            Others
                        </option>
                    </select>
                    <span class="absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                        <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                </div>
            </div>

            <div class="w-full px-2.5">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Date of Birth
                </label>
                <x-form.date-picker
                    id="datepickerTwo"
                    placeholder="Select date"
                />
            </div>

            <div class="w-full px-2.5">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Category
                </label>
                <div x-data="{ isOptionSelected: false }" class="relative z-20 bg-transparent">
                    <select
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-3 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        :class="isOptionSelected && 'text-gray-500 dark:text-gray-400'"
                        @change="isOptionSelected = true">
                        <option value="" class="text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                            Category 1
                        </option>
                        <option value="" class="text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                            Category 2
                        </option>
                        <option value="" class="text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                            Category 3
                        </option>
                    </select>
                    <span class="absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                        <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                </div>
            </div>

            <div class="w-full px-2.5">
                <h4
                    class="border-b border-gray-200 pb-4 text-base font-medium text-gray-800 dark:border-gray-800 dark:text-white/90">
                    Address
                </h4>
            </div>

            <div class="w-full px-2.5">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Street
                </label>
                <input type="text"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
            </div>

            <div class="w-full px-2.5 xl:w-1/2">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    City
                </label>
                <input type="text"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
            </div>

            <div class="w-full px-2.5 xl:w-1/2">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    State
                </label>
                <input type="text"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
            </div>

            <div class="w-full px-2.5 xl:w-1/2">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Post Code
                </label>
                <input type="text"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
            </div>

            <div class="w-full px-2.5 xl:w-1/2">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Country
                </label>
                <div x-data="{ isOptionSelected: false }" class="relative z-20 bg-transparent">
                    <select
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-3 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        :class="isOptionSelected && 'text-gray-500 dark:text-gray-400'"
                        @change="isOptionSelected = true">
                        <option value="" class="text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                            --Select Country--
                        </option>
                        <option value="" class="text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                            USA
                        </option>
                        <option value="" class="text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                            Canada
                        </option>
                    </select>
                    <span class="absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                        <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20"
                            fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                </div>
            </div>

            <div class="w-full px-2.5">
                <div class="flex items-center gap-3" x-data="{ isChecked: '' }">
                    <label class="text-sm font-medium text-gray-800 dark:text-white/90">
                        Membership:
                    </label>

                    <div class="flex flex-wrap items-center gap-4">
                        <div>
                            <label
                                :class="isChecked === 'Free' ? 'text-gray-700 dark:text-gray-400' :
                                    'text-gray-500 dark:text-gray-400'"
                                class="relative flex cursor-pointer items-center gap-3 text-sm font-medium select-none">
                                <input class="sr-only" type="radio" name="roleSelect" id="Free"
                                    @change="isChecked = 'Free'" />
                                <span
                                    :class="isChecked === 'Free' ? 'border-brand-500 bg-brand-500' :
                                        'bg-transparent border-gray-300 dark:border-gray-700'"
                                    class="flex h-5 w-5 items-center justify-center rounded-full border-[1.25px]">
                                    <span :class="isChecked === 'Free' ? 'block' : 'hidden'"
                                        class="h-2 w-2 rounded-full bg-white"></span>
                                </span>
                                Free
                            </label>
                        </div>

                        <div>
                            <label
                                :class="isChecked === 'Paid' ? 'text-gray-700 dark:text-gray-400' :
                                    'text-gray-500 dark:text-gray-400'"
                                class="relative flex cursor-pointer items-center gap-3 text-sm font-medium select-none">
                                <input class="sr-only" type="radio" name="roleSelect" id="Paid"
                                    @change="isChecked = 'Paid'" />
                                <span
                                    :class="isChecked === 'Paid' ? 'border-brand-500 bg-brand-500' :
                                        'bg-transparent border-gray-300 dark:border-gray-700'"
                                    class="flex h-5 w-5 items-center justify-center rounded-full border-[1.25px]">
                                    <span :class="isChecked === 'Paid' ? 'block' : 'hidden'"
                                        class="h-2 w-2 rounded-full bg-white"></span>
                                </span>
                                Paid
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="w-full px-2.5">
                <div class="mt-1 flex items-center gap-3">
                    <button type="submit"
                        class="bg-brand-500 hover:bg-brand-600 flex items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white">
                        Save Changes
                    </button>

                    <button
                        class="flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </form>
</x-common.component-card>
