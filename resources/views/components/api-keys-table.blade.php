@props(['apiKeys'])

<div x-data='apiKeysTable(@json($apiKeys))'>
    <div class="rounded-2xl border border-gray-200 bg-white px-6 pl-5 dark:border-gray-800 dark:bg-white/3">
        <div class="flex flex-col justify-between gap-5 border-b border-gray-100 py-4 sm:flex-row sm:items-center dark:border-gray-800">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">API Keys</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    API keys are used to authenticate requests to the tailadmin API
                </p>
            </div>
            <div>
                <button @click="openModal" class="bg-blue-500 hover:bg-blue-600 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M5 10.0002H15.0006M10.0002 5V15.0006" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Add API Key
                </button>

                <!-- Modal -->
                <div x-show="isModalOpen" x-cloak class="modal fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-5">
                    <div @click="closeModal" class="modal-close-btn fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"></div>
                    <div class="relative w-full max-w-[600px] rounded-3xl bg-white p-6 lg:p-10 dark:bg-gray-900">
                        <!-- Close Button -->
                        <button @click="closeModal" class="absolute top-3 right-3 z-999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 sm:top-6 sm:right-6 sm:h-11 sm:w-11 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                            <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" fill="" />
                            </svg>
                        </button>

                        <div>
                            <h4 class="text-2xl mb-1 font-semibold text-gray-800 dark:text-white/90">
                                Generate API key
                            </h4>
                            <p class="mb-7 text-sm leading-6 text-gray-500 dark:text-gray-400">
                                To enable secure access to the web services, your app requires an API key with
                                permissions for resources such as the S3 bucket.
                            </p>
                            <form @submit.prevent="generateApiKey">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Enter your application name
                                    </label>
                                    <input type="text" x-model="appName" class="shadow-theme-xs focus:border-blue-300 focus:ring-blue-500/10 dark:focus:border-blue-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>
                                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                    Naming your application makes it easier to recognize your API key in the future.
                                </p>
                            </form>
                            <div class="mt-8 flex w-full items-center justify-between gap-3">
                                <button @click="closeModal" type="button" class="shadow-theme-xs flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                                    Close
                                </button>
                                <button @click="generateApiKey" type="button" :disabled="isGenerating" class="bg-blue-500 shadow-theme-xs hover:bg-blue-600 flex w-full justify-center rounded-lg px-4 py-3 text-sm font-medium text-white disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span x-show="!isGenerating">Generate API key</span>
                                    <span x-show="isGenerating">Generating...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="custom-scrollbar overflow-x-auto px-1 pb-4">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <th class="py-3 pr-5 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Name</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Created</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Last used</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">
                            Disable/Enable
                        </th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <template x-if="apiKeys.length === 0">
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No API keys found. Click "Add API Key" to create one.
                            </td>
                        </tr>
                    </template>
                    <template x-for="(key, index) in apiKeys" :key="index">
                        <tr :class="{ 'opacity-50 pointer-events-none': key.isLoading }">
                            <td class="py-3 pr-5 whitespace-nowrap">
                                <div>
                                    <label :for="'api-' + index" class="mb-2 inline-block text-sm text-gray-700 dark:text-gray-400" x-text="key.name"></label>
                                    <div class="flex items-center gap-3">
                                        <div class="relative">
                                            <input :value="key.value" type="text" :id="'api-' + index" readonly class="shadow-theme-xs h-11 w-full min-w-[360px] rounded-lg border border-gray-300 bg-transparent py-3 pr-[90px] pl-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                            <button type="button" @click="copyKey(index)" class="copy-btn absolute top-1/2 right-0 inline-flex h-11 -translate-y-1/2 cursor-pointer items-center gap-1 rounded-r-lg border border-gray-300 py-3 pr-3 pl-3.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                                <span class="copy-icon" :class="{ 'hidden': key.copied }">
                                                    <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M6.58822 4.58398C6.58822 4.30784 6.81207 4.08398 7.08822 4.08398H15.4154C15.6915 4.08398 15.9154 4.30784 15.9154 4.58398L15.9154 12.9128C15.9154 13.189 15.6916 13.4128 15.4154 13.4128H7.08821C6.81207 13.4128 6.58822 13.189 6.58822 12.9128V4.58398ZM7.08822 2.58398C5.98365 2.58398 5.08822 3.47942 5.08822 4.58398V5.09416H4.58496C3.48039 5.09416 2.58496 5.98959 2.58496 7.09416V15.4161C2.58496 16.5207 3.48039 17.4161 4.58496 17.4161H12.9069C14.0115 17.4161 14.9069 16.5207 14.9069 15.4161L14.9069 14.9128H15.4154C16.52 14.9128 17.4154 14.0174 17.4154 12.9128L17.4154 4.58398C17.4154 3.47941 16.52 2.58398 15.4154 2.58398H7.08822ZM13.4069 14.9128H7.08821C5.98364 14.9128 5.08822 14.0174 5.08822 12.9128V6.59416H4.58496C4.30882 6.59416 4.08496 6.81801 4.08496 7.09416V15.4161C4.08496 15.6922 4.30882 15.9161 4.58496 15.9161H12.9069C13.183 15.9161 13.4069 15.6922 13.4069 15.4161L13.4069 14.9128Z" fill="" />
                                                    </svg>
                                                </span>
                                                <span class="check-icon" :class="{ 'hidden': !key.copied }">
                                                    <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M16.707 6.293a1 1 0 00-1.414 0L9 12.586l-2.293-2.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l7-7a1 1 0 000-1.414z" fill="currentColor" />
                                                    </svg>
                                                </span>
                                                <div class="copy-text" x-text="key.copied ? 'Copied' : 'Copy'"></div>
                                            </button>
                                        </div>

                                        <div class="group relative inline-block">
                                            <button @click="regenerateKey(index)" class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-gray-300 text-gray-700 dark:border-gray-700 dark:text-gray-400">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                                    <path d="M17.0436 8.11306C16.6282 6.56272 15.7128 5.19276 14.4395 4.21568C13.1661 3.2386 11.6059 2.70898 10.0009 2.70898C8.39585 2.70898 6.83566 3.2386 5.5623 4.21568C4.28894 5.19276 3.37357 6.56271 2.95816 8.11306C2.87345 8.42919 2.81944 8.65089 2.78711 8.80352M2.9559 11.8866C3.37131 13.437 4.28668 14.8069 5.56004 15.784C6.8334 16.7611 8.39359 17.2907 9.99862 17.2907C11.6037 17.2907 13.1638 16.7611 14.4372 15.784C15.7106 14.8069 16.6259 13.437 17.0414 11.8866C17.1278 11.5641 17.1826 11.3399 17.2152 11.1871M5.4327 7.49705L2.86544 8.94265L2.78711 8.80352M1.41992 6.37512L2.78711 8.80352M14.575 12.503L17.1422 11.0574L17.2152 11.1871M18.5877 13.6249L17.2152 11.1871" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                            </button>
                                            <div class="invisible absolute bottom-full left-1/2 z-9999 mb-2.5 -translate-x-1/2 opacity-0 transition-opacity duration-300 group-hover:visible group-hover:opacity-100">
                                                <div class="relative">
                                                    <div class="rounded-lg bg-white px-3 py-2 text-xs font-medium whitespace-nowrap text-gray-700 shadow-xs dark:bg-[#1E2634] dark:text-white">Regenerate</div>
                                                    <div class="absolute -bottom-1 left-1/2 h-3 w-4 -translate-x-1/2 rotate-45 bg-white dark:bg-[#1E2634]"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center justify-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="key.active ? 'bg-green-50 text-green-600 dark:bg-green-500/15 dark:text-green-500' :
                                        'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-500'"
                                    x-text="key.active ? 'Active' : 'Disabled'"></span>
                            </td>
                            <td class="px-5 py-3 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400" x-text="key.created"></td>
                            <td class="px-5 py-3 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400" x-text="key.lastUsed"></td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <div>
                                    <label :for="'toggle-' + index" class="cursor-pointer">
                                        <div class="relative">
                                            <input type="checkbox" :id="'toggle-' + index" class="sr-only" @change="toggleActive(index)" :checked="key.active" />
                                            <div class="block h-6 w-11 rounded-full" :class="key.active ? 'bg-blue-500 dark:bg-blue-500' : 'bg-gray-200 dark:bg-white/10'">
                                            </div>
                                            <div :class="key.active ? 'translate-x-full' : 'translate-x-0'"
                                                class="shadow-theme-sm absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white duration-200 ease-linear">
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <div class="flex w-full items-center gap-3">
                                    <button @click="deleteKey(index)" class="hover:text-red-500 dark:hover:text-red-500 text-gray-500 dark:text-gray-400">
                                        <svg class="fill-current" width="21" height="21" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M7.04142 4.29199C7.04142 3.04935 8.04878 2.04199 9.29142 2.04199H11.7081C12.9507 2.04199 13.9581 3.04935 13.9581 4.29199V4.54199H16.1252H17.166C17.5802 4.54199 17.916 4.87778 17.916 5.29199C17.916 5.70621 17.5802 6.04199 17.166 6.04199H16.8752V8.74687V13.7469V16.7087C16.8752 17.9513 15.8678 18.9587 14.6252 18.9587H6.37516C5.13252 18.9587 4.12516 17.9513 4.12516 16.7087V13.7469V8.74687V6.04199H3.8335C3.41928 6.04199 3.0835 5.70621 3.0835 5.29199C3.0835 4.87778 3.41928 4.54199 3.8335 4.54199H4.87516H7.04142V4.29199ZM15.3752 13.7469V8.74687V6.04199H13.9581H13.2081H7.79142H7.04142H5.62516V8.74687V13.7469V16.7087C5.62516 17.1229 5.96095 17.4587 6.37516 17.4587H14.6252C15.0394 17.4587 15.3752 17.1229 15.3752 16.7087V13.7469ZM8.54142 4.54199H12.4581V4.29199C12.4581 3.87778 12.1223 3.54199 11.7081 3.54199H9.29142C8.87721 3.54199 8.54142 3.87778 8.54142 4.29199V4.54199ZM8.8335 8.50033C9.24771 8.50033 9.5835 8.83611 9.5835 9.25033V14.2503C9.5835 14.6645 9.24771 15.0003 8.8335 15.0003C8.41928 15.0003 8.0835 14.6645 8.0835 14.2503V9.25033C8.0835 8.83611 8.41928 8.50033 8.8335 8.50033ZM12.9168 9.25033C12.9168 8.83611 12.581 8.50033 12.1668 8.50033C11.7526 8.50033 11.4168 8.83611 11.4168 9.25033V14.2503C11.4168 14.6645 11.7526 15.0003 12.1668 15.0003C12.581 15.0003 12.9168 14.6645 12.9168 14.2503V9.25033Z" fill="currentColor" />
                                        </svg>
                                    </button>
                                    <button @click="editKey(index)" class="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90">
                                        <svg class="fill-current" width="21" height="21" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M17.0911 3.53206C16.2124 2.65338 14.7878 2.65338 13.9091 3.53206L5.6074 11.8337C5.29899 12.1421 5.08687 12.5335 4.99684 12.9603L4.26177 16.445C4.20943 16.6931 4.286 16.9508 4.46529 17.1301C4.64458 17.3094 4.90232 17.3859 5.15042 17.3336L8.63507 16.5985C9.06184 16.5085 9.45324 16.2964 9.76165 15.988L18.0633 7.68631C18.942 6.80763 18.942 5.38301 18.0633 4.50433L17.0911 3.53206ZM14.9697 4.59272C15.2626 4.29982 15.7375 4.29982 16.0304 4.59272L17.0027 5.56499C17.2956 5.85788 17.2956 6.33276 17.0027 6.62565L16.1043 7.52402L14.0714 5.49109L14.9697 4.59272ZM13.0107 6.55175L6.66806 12.8944C6.56526 12.9972 6.49455 13.1277 6.46454 13.2699L5.96704 15.6283L8.32547 15.1308C8.46772 15.1008 8.59819 15.0301 8.70099 14.9273L15.0436 8.58468L13.0107 6.55175Z" fill="currentColor" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    window.apiKeysTable = function(apiKeys) {
        return {
            isModalOpen: false,
            appName: '',
            apiKeys: apiKeys,
            isGenerating: false,
            init() {
                this.apiKeys = this.apiKeys.map(key => this.formatKey(key));
            },
            openModal() {
                this.isModalOpen = true;
            },
            closeModal() {
                this.isModalOpen = false;
            },
            formatKey(key) {
                return {
                    ...key,
                    actualValue: key.key,
                    value: this.maskKey(key.key),
                    copied: false,
                    created: new Date(key.created_at).toLocaleDateString('en-US', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric'
                    }),
                    lastUsed: key.last_used_at ? new Date(key.last_used_at).toLocaleString() : 'Never',
                    active: Boolean(key.active),
                    isLoading: false
                };
            },
            maskKey(key) {
                if (!key || key.length < 10) return key;
                const prefix = key.substring(0, 8);
                const lastFour = key.substring(key.length - 4);
                return `${prefix}**********${lastFour}`;
            },
            copyKey(index) {
                navigator.clipboard.writeText(this.apiKeys[index].actualValue).then(() => {
                    this.apiKeys[index].copied = true;
                    setTimeout(() => {
                        this.apiKeys[index].copied = false;
                    }, 2000);
                });
            },
            async regenerateKey(index) {
                this.apiKeys[index].isLoading = true;
                const key = this.apiKeys[index];
                try {
                    const response = await fetch(`/api-keys/${key.id}/regenerate`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').getAttribute('content')
                        }
                    });
                    const data = await response.json();
                    if (response.ok) {
                        this.apiKeys[index] = this.formatKey(data.apiKey);
                    } else {
                        alert('Failed to regenerate key');
                    }
                } catch (error) {
                    console.error('Error:', error);
                } finally {
                    this.apiKeys[index].isLoading = false;
                }
            },
            async deleteKey(index) {
                if (confirm('Are you sure you want to delete this API key?')) {
                    this.apiKeys[index].isLoading = true;
                    const key = this.apiKeys[index];
                    try {
                        const response = await fetch(`/api-keys/${key.id}`, {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').getAttribute('content')
                            }
                        });
                        if (response.ok) {
                            this.apiKeys.splice(index, 1);
                        } else {
                            alert('Failed to delete key');
                            this.apiKeys[index].isLoading = false;
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.apiKeys[index].isLoading = false;
                    }
                }
            },
            async editKey(index) {
                const key = this.apiKeys[index];
                const newName = prompt('Enter new name for API key:', key.name);
                if (newName && newName.trim() && newName !== key.name) {
                    this.apiKeys[index].isLoading = true;
                    try {
                        const response = await fetch(`/api-keys/${key.id}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').getAttribute('content')
                            },
                            body: JSON.stringify({ name: newName.trim() })
                        });
                        const data = await response.json();
                        if (response.ok) {
                            this.apiKeys[index].name = data.apiKey.name;
                        } else {
                            alert('Failed to update key name');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                    } finally {
                        this.apiKeys[index].isLoading = false;
                    }
                }
            },
            async toggleActive(index) {
                // Optimistic update
                this.apiKeys[index].active = !this.apiKeys[index].active;
                
                const key = this.apiKeys[index];
                try {
                    const response = await fetch(`/api-keys/${key.id}/toggle`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').getAttribute('content')
                        }
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        // Revert on failure
                        this.apiKeys[index].active = !this.apiKeys[index].active;
                        alert('Failed to toggle status');
                    }
                } catch (error) {
                    // Revert on error
                    this.apiKeys[index].active = !this.apiKeys[index].active;
                    console.error('Error:', error);
                }
            },
            async generateApiKey() {
                if (this.appName.trim()) {
                    this.isGenerating = true;
                    try {
                        const response = await fetch('/api-keys', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').getAttribute('content')
                            },
                            body: JSON.stringify({ name: this.appName })
                        });
                        const data = await response.json();
                        if (response.ok) {
                            this.apiKeys.unshift(this.formatKey(data.apiKey));
                            this.closeModal();
                            this.appName = '';
                        } else {
                            alert('Failed to generate key');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                    } finally {
                        this.isGenerating = false;
                    }
                }
            }
        };
    }
</script>

<style>
    [x-cloak] {
        display: none;
    }
</style>
