
   <div x-data="{
        modalOpen: false,
        formData: {
            firstName: 'Mushafrof',
            lastName: 'Chowdhury',
            street: '800 E Elcamino Real, suite #400',
            country: 'USA',
            city: 'New York',
            zipCode: '19029',
            vatNumber: 'DE4920348'
        },
        handleSave() {
            // Handle save logic here
            console.log('Saving changes...', this.formData);
            this.modalOpen = false;
        }
    }" class="rounded-2xl border border-gray-200 bg-white xl:w-2/6 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="px-6 py-5">
            <h3 class="text-base font-medium text-gray-800 dark:text-white/90">
                Billing Info
            </h3>
        </div>
        <div class="border-t border-gray-200 p-4 sm:p-6 dark:border-gray-800">
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                <li class="flex items-center gap-5 py-2.5">
                    <span class="w-1/2 text-sm text-gray-500 sm:w-1/3 dark:text-gray-400">
                        Name
                    </span>
                    <span class="w-1/2 text-sm font-medium text-gray-700 sm:w-2/3 dark:text-gray-400">
                        <span x-text="formData.firstName + ' ' + formData.lastName"></span>
                    </span>
                </li>
                <li class="flex items-center gap-5 py-2.5">
                    <span class="w-1/2 text-sm text-gray-500 sm:w-1/3 dark:text-gray-400">
                        Street
                    </span>
                    <span class="w-1/2 text-sm font-medium text-gray-700 sm:w-2/3 dark:text-gray-400"
                        x-text="formData.street"></span>
                </li>
                <li class="flex items-center gap-5 py-2.5">
                    <span class="w-1/2 text-sm text-gray-500 sm:w-1/3 dark:text-gray-400">
                        City/State
                    </span>
                    <span class="w-1/2 text-sm font-medium text-gray-700 sm:w-2/3 dark:text-gray-400">
                        Mountain View, CA, 94040
                    </span>
                </li>
                <li class="flex items-center gap-5 py-2.5">
                    <span class="w-1/2 text-sm text-gray-500 sm:w-1/3 dark:text-gray-400">
                        Country
                    </span>
                    <span class="w-1/2 text-sm font-medium text-gray-700 sm:w-2/3 dark:text-gray-400">
                        United States of America
                    </span>
                </li>
                <li class="flex items-center gap-5 py-2.5">
                    <span class="w-1/2 text-sm text-gray-500 sm:w-1/3 dark:text-gray-400">
                        Zip/Postal code
                    </span>
                    <span class="w-1/2 text-sm font-medium text-gray-700 sm:w-2/3 dark:text-gray-400"
                        x-text="formData.zipCode"></span>
                </li>
                <li class="flex items-center gap-5 py-2.5">
                    <span class="w-1/2 text-sm text-gray-500 sm:w-1/3 dark:text-gray-400">
                        Town/City
                    </span>
                    <span class="w-1/2 text-sm font-medium text-gray-700 sm:w-2/3 dark:text-gray-400"
                        x-text="formData.city"></span>
                </li>
                <li class="flex items-center gap-5 py-2.5">
                    <span class="w-1/2 text-sm text-gray-500 sm:w-1/3 dark:text-gray-400">
                        VAT Number
                    </span>
                    <span class="w-1/2 text-sm font-medium text-gray-700 sm:w-2/3 dark:text-gray-400"
                        x-text="formData.vatNumber"></span>
                </li>
            </ul>

            <div class="mt-10 xl:mt-2 2xl:mt-12">
                <button @click="modalOpen = true" type="button"
                    class="shadow-theme-xs flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" width="21" height="20" viewBox="0 0 21 20"
                        fill="none">
                        <path
                            d="M12.8861 5.08135L15.4182 7.61345M16.1437 3.59219L16.908 4.35652C17.3962 4.84468 17.3962 5.63613 16.908 6.12429L8.33547 14.6968C8.19039 14.8419 8.01182 14.9491 7.81554 15.0088L4.47461 16.0256L5.49141 12.6847C5.55115 12.4884 5.65829 12.3098 5.80337 12.1647L14.3759 3.59219C14.8641 3.10404 15.6555 3.10404 16.1437 3.59219Z"
                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Update Billing Address
                </button>

                <!-- Modal -->
                <div x-show="modalOpen" x-cloak @keydown.escape.window="modalOpen = false"
                    class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto">

                    <!-- Backdrop -->
                    <div @click="modalOpen = false"
                        class="fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"
                        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                    </div>

                    <!-- Modal Content -->
                    <div @click.stop
                        class="relative m-5 w-full max-w-[558px] rounded-3xl bg-white p-6 dark:bg-gray-900 sm:m-0 lg:p-10"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 transform scale-95"
                        x-transition:enter-end="opacity-100 transform scale-100"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 transform scale-100"
                        x-transition:leave-end="opacity-0 transform scale-95">

                        <!-- Close Button -->
                        <button @click="modalOpen = false"
                            class="absolute right-3 top-3 z-999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white sm:right-6 sm:top-6 sm:h-11 sm:w-11">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z"
                                    fill="currentColor" />
                            </svg>
                        </button>

                        <!-- Modal Body -->
                        <div>
                            <div class="px-1">
                                <h4 class="text-title-xs mb-1 font-semibold text-gray-800 dark:text-white/90">
                                    New integration
                                </h4>
                                <p class="mb-7 text-sm leading-6 text-gray-500 dark:text-gray-400">
                                    Set up an integration and add a brief explanation for the team.
                                </p>
                            </div>

                            <div class="custom-scrollbar h-[490px] overflow-y-auto px-1 sm:h-auto">
                                <form @submit.prevent="handleSave">
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <label
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                First Name
                                            </label>
                                            <input type="text" x-model="formData.firstName"
                                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                        </div>
                                        <div>
                                            <label
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                Last Name
                                            </label>
                                            <input type="text" x-model="formData.lastName"
                                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                        </div>
                                        <div class="sm:col-span-full">
                                            <label
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                Street
                                            </label>
                                            <input type="text" x-model="formData.street"
                                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                        </div>
                                        <div class="sm:col-span-1">
                                            <label
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                Country
                                            </label>
                                            <div class="relative z-20 bg-transparent">
                                                <select x-model="formData.country"
                                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                                    <option value=""
                                                        class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                                                        Select Option
                                                    </option>
                                                    <option value="USA"
                                                        class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                                                        USA
                                                    </option>
                                                    <option value="UK"
                                                        class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                                                        UK
                                                    </option>
                                                    <option value="BD"
                                                        class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                                                        BD
                                                    </option>
                                                    <option value="EU"
                                                        class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                                                        EU
                                                    </option>
                                                    <option value="ID"
                                                        class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                                                        ID
                                                    </option>
                                                </select>
                                                <span
                                                    class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                                    <svg class="stroke-current" width="20" height="20"
                                                        viewBox="0 0 20 20" fill="none"
                                                        xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396"
                                                            stroke="" stroke-width="1.5" stroke-linecap="round"
                                                            stroke-linejoin="round" />
                                                    </svg>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="sm:col-span-1">
                                            <label
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                Town/City
                                            </label>
                                            <div class="relative z-20 bg-transparent">
                                                <select x-model="formData.city"
                                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                                    <option value=""
                                                        class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                                                        Select Option
                                                    </option>
                                                    <option value="New York"
                                                        class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                                                        New York
                                                    </option>
                                                    <option value="Tokyo"
                                                        class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                                                        Tokyo
                                                    </option>
                                                    <option value="Chicago"
                                                        class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                                                        Chicago
                                                    </option>
                                                    <option value="Los Angels"
                                                        class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                                                        Los Angels
                                                    </option>
                                                    <option value="Berlin"
                                                        class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                                                        Berlin
                                                    </option>
                                                </select>
                                                <span
                                                    class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                                    <svg class="stroke-current" width="20" height="20"
                                                        viewBox="0 0 20 20" fill="none"
                                                        xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396"
                                                            stroke="" stroke-width="1.5" stroke-linecap="round"
                                                            stroke-linejoin="round" />
                                                    </svg>
                                                </span>
                                            </div>
                                        </div>
                                        <div>
                                            <label
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                Zip/Postal code
                                            </label>
                                            <input type="text" x-model="formData.zipCode"
                                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                        </div>
                                        <div>
                                            <label
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                VAT Number
                                            </label>
                                            <input type="text" x-model="formData.vatNumber"
                                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                        </div>
                                    </div>

                                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                        Click "Update Info" to update your billing information.
                                    </p>
                                </form>
                            </div>

                            <div class="mt-8 flex items-center justify-end gap-3">
                                <button @click="modalOpen = false" type="button"
                                    class="shadow-theme-xs flex justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                                    Close
                                </button>
                                <button @click="handleSave" type="button"
                                    class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 flex justify-center rounded-lg px-4 py-3 text-sm font-medium text-white">
                                    Update Info
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
