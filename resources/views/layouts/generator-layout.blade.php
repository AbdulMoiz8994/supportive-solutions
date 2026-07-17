{{--
    This is the main layout for AI generator/chat interface
    You can pass content via the $slot variable or use @section('content')
--}}

@php
    // Chat data - you can pass these from controller
    $todayChats = $todayChats ?? [
        ['id' => '1', 'title' => 'Write a follow-up email to a client'],
        ['id' => '2', 'title' => 'Generate responsive login form layout'],
        ['id' => '3', 'title' => 'Create a warning state modal'],
        ['id' => '4', 'title' => 'Suggest color palette for dark theme'],
    ];
    $yesterdayChats = $yesterdayChats ?? [
        ['id' => '5', 'title' => 'Improve login page accessibility'],
        ['id' => '6', 'title' => 'Create a warning state modal with animation'],
        ['id' => '7', 'title' => 'Add password visibility toggle'],
        ['id' => '8', 'title' => 'Write validation logic for login form...'],
        ['id' => '9', 'title' => 'Fix mobile responsiveness of login UI...'],
    ];
    $lastWeekChats = $lastWeekChats ?? [
        ['id' => '10', 'title' => 'Improve login page accessibility...'],
        ['id' => '11', 'title' => 'Improve login page accessibility...'],
    ];
@endphp

<div
    x-data="{
        sidebarOpen: false,
        promptText: '',
        closeSidebar() {
            this.sidebarOpen = false;
        },
        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
        },
        handleAttach() {
            console.log('Attach file');
            // Add file upload logic here
        },
        handleSend() {
            if (this.promptText.trim()) {
                console.log('Send prompt:', this.promptText);
                // Add send logic here - could be AJAX/Livewire
                this.promptText = '';
            }
        }
    }"
    class="relative h-[calc(100vh-134px)] xl:h-[calc(100vh-146px)] px-4 xl:flex xl:px-0">

    <!-- Mobile Header -->
    <div class="my-6 flex items-center justify-between rounded-2xl border border-gray-200 bg-white p-3 xl:hidden dark:border-gray-800 dark:bg-gray-900">
        <h4 class="pl-2 text-lg font-medium text-gray-800 dark:text-white/90">
            Chats History
        </h4>
        <button
            @click="toggleSidebar()"
            class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-gray-300 text-gray-700 dark:border-gray-700 dark:text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                <path d="M4 6L20 6M4 18L20 18M4 12L20 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 xl:py-10">
        <div class="relative mx-auto items-center max-w-[720px]">

            <!-- Content Slot -->
            <div class="mb-32">
                {{ $slot ?? '' }}
                @yield('content')
            </div>

            <!-- Fixed Input Wrapper -->
            <div class="fixed bottom-5 lg:bottom-10 left-1/2 z-20 w-full -translate-x-1/2 transform px-4 sm:px-6 lg:px-8">
                <!-- Container with max width -->
                <div class="mx-auto w-full max-w-[720px] rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-gray-800">

                    <!-- Textarea -->
                    <textarea
                        x-model="promptText"
                        @keydown.enter.ctrl="handleSend()"
                        @keydown.enter.meta="handleSend()"
                        placeholder="Type your prompt here..."
                        class="h-20 w-full resize-none border-none bg-transparent p-0 font-normal text-gray-800 outline-none placeholder:text-gray-400 focus:ring-0 dark:text-white"></textarea>

                    <!-- Bottom Section -->
                    <div class="flex items-center justify-between pt-2">
                        <button
                            @click="handleAttach()"
                            class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                            <!-- Attach Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none">
                                <path d="M14.4194 11.7679L15.4506 10.7367C17.1591 9.02811 17.1591 6.25802 15.4506 4.54947C13.742 2.84093 10.9719 2.84093 9.2634 4.54947L8.2322 5.58067M11.77 14.4172L10.7365 15.4507C9.02799 17.1592 6.2579 17.1592 4.54935 15.4507C2.84081 13.7422 2.84081 10.9721 4.54935 9.26352L5.58285 8.23002M11.7677 8.23232L8.2322 11.7679" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Attach
                        </button>

                        <!-- Send Button -->
                        <button
                            @click="handleSend()"
                            :disabled="!promptText.trim()"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-gray-900 text-white transition hover:bg-gray-800 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-white/90 dark:text-gray-800 dark:hover:bg-gray-900 dark:hover:text-white/90">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none">
                                <path d="M9.99674 3.33252L9.99675 16.667M5 8.32918L9.99984 3.33252L15 8.32918" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar Section -->
    <div class="relative">
        <!-- Backdrop -->
        <div
            x-show="sidebarOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="closeSidebar()"
            class="fixed inset-0 z-[99999] bg-black/50 xl:hidden dark:bg-black/80">

            <!-- Close Button -->
            <div class="absolute top-4 right-[300px]">
                <button
                    @click="closeSidebar()"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white text-gray-800 transition hover:bg-gray-100 dark:bg-gray-800 dark:text-white/90 dark:hover:bg-white/3 hover:dark:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M6.75104 17.249L17.249 6.75111M6.75104 6.75098L17.249 17.2489" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Sidebar History Component -->
        <x-ai.ai-sidebar-history
            :is-sidebar-open="false"
            :today-chats="$todayChats"
            :yesterday-chats="$yesterdayChats"
            :last-week-chats="$lastWeekChats"
        />
    </div>
</div>
