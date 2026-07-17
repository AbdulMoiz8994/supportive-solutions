<div x-data="kanbanBoard()" @dragover.prevent class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <!-- Task header Start -->
    <x-task.task-header />
    <!-- Task header End -->

    <!-- Task wrapper Start -->
    <div class="mt-7 grid grid-cols-1 border-t border-gray-200 sm:mt-0 sm:grid-cols-2 xl:grid-cols-3 dark:border-gray-800">

        <!-- Loop through swim lanes -->
        <template x-for="(lane, index) in lanes" :key="lane.id">
            <div :class="{ 'border-x border-gray-200 dark:border-gray-800': index === 1 }"
                class="swim-lane flex flex-col gap-5 p-4 xl:p-6" @dragover="handleDragOver($event, lane.id)"
                @drop="handleDrop($event, lane.id)">
                <div class="mb-1 flex items-center justify-between">
                    <h3 class="flex items-center gap-3 text-base font-medium text-gray-800 dark:text-white/90">
                        <span x-text="lane.name"></span>
                        <span :class="lane.badgeClass" class="text-theme-xs inline-flex rounded-full px-2 py-0.5 font-medium" x-text="lane.tasks.length">
                        </span>
                    </h3>

                    <div x-data="{ openDropDown: false }" class="relative">
                        <button @click="openDropDown = !openDropDown" class="text-gray-700 dark:text-gray-400">
                            <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M5.99902 10.2451C6.96552 10.2451 7.74902 11.0286 7.74902 11.9951V12.0051C7.74902 12.9716 6.96552 13.7551 5.99902 13.7551C5.03253 13.7551 4.24902 12.9716 4.24902 12.0051V11.9951C4.24902 11.0286 5.03253 10.2451 5.99902 10.2451ZM17.999 10.2451C18.9655 10.2451 19.749 11.0286 19.749 11.9951V12.0051C19.749 12.9716 18.9655 13.7551 17.999 13.7551C17.0325 13.7551 16.249 12.9716 16.249 12.0051V11.9951C16.249 11.0286 17.0325 10.2451 17.999 10.2451ZM13.749 11.9951C13.749 11.0286 12.9655 10.2451 11.999 10.2451C11.0325 10.2451 10.249 11.0286 10.249 11.9951V12.0051C10.249 12.9716 11.0325 13.7551 11.999 13.7551C12.9655 13.7551 13.749 12.9716 13.749 12.0051V11.9951Z"
                                    fill="" />
                            </svg>
                        </button>
                        <div x-show="openDropDown" @click.outside="openDropDown = false" x-transition
                            class="shadow-theme-md dark:bg-gray-dark absolute top-full right-0 z-40 w-[140px] space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800">
                            <button
                                class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">
                                Edit
                            </button>
                            <button
                                class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">
                                Delete
                            </button>
                            <button @click="clearLane(lane.id)"
                                class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">
                                Clear All
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Task items loop -->
                <template x-for="task in lane.tasks" :key="task.id">
                    <div draggable="true" @dragstart="handleDragStart($event, task.id, lane.id)"
                        @dragend="handleDragEnd($event)"
                        class="task shadow-theme-sm rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/5 cursor-move transition-opacity"
                        :class="{ 'opacity-100': draggingTaskId === task.id }">
                        <div class="flex items-start justify-between gap-6">
                            <div class="flex-1">
                                <h4 class="mb-5 text-base text-gray-800 dark:text-white/90" x-text="task.title">
                                </h4>

                                <!-- Description if exists -->
                                <template x-if="task.description">
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4" x-text="task.description">
                                    </p>
                                </template>

                                <!-- Image if exists -->
                                <template x-if="task.image">
                                    <div class="my-4">
                                        <img :src="task.image" :alt="task.title"
                                            class="overflow-hidden rounded-xl border-[0.5px] border-gray-200 dark:border-gray-800" />
                                    </div>
                                </template>

                                <div class="flex items-center gap-3">
                                    <!-- Date -->
                                    <template x-if="task.date">
                                        <span
                                            class="flex cursor-pointer items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
                                            <svg class="fill-current" width="16" height="16" viewBox="0 0 16 16"
                                                fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M5.33329 1.0835C5.74751 1.0835 6.08329 1.41928 6.08329 1.8335V2.25016L9.91663 2.25016V1.8335C9.91663 1.41928 10.2524 1.0835 10.6666 1.0835C11.0808 1.0835 11.4166 1.41928 11.4166 1.8335V2.25016L12.3333 2.25016C13.2998 2.25016 14.0833 3.03366 14.0833 4.00016V6.00016L14.0833 12.6668C14.0833 13.6333 13.2998 14.4168 12.3333 14.4168L3.66663 14.4168C2.70013 14.4168 1.91663 13.6333 1.91663 12.6668L1.91663 6.00016L1.91663 4.00016C1.91663 3.03366 2.70013 2.25016 3.66663 2.25016L4.58329 2.25016V1.8335C4.58329 1.41928 4.91908 1.0835 5.33329 1.0835ZM5.33329 3.75016L3.66663 3.75016C3.52855 3.75016 3.41663 3.86209 3.41663 4.00016V5.25016L12.5833 5.25016V4.00016C12.5833 3.86209 12.4714 3.75016 12.3333 3.75016L10.6666 3.75016L5.33329 3.75016ZM12.5833 6.75016L3.41663 6.75016L3.41663 12.6668C3.41663 12.8049 3.52855 12.9168 3.66663 12.9168L12.3333 12.9168C12.4714 12.9168 12.5833 12.8049 12.5833 12.6668L12.5833 6.75016Z"
                                                    fill="" />
                                            </svg>
                                            <span x-text="task.date"></span>
                                        </span>
                                    </template>

                                    <!-- Comments -->
                                    <template x-if="task.comments">
                                        <span
                                            class="flex cursor-pointer items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
                                            <svg class="stroke-current" width="18" height="18"
                                                viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M9 15.6343C12.6244 15.6343 15.5625 12.6961 15.5625 9.07178C15.5625 5.44741 12.6244 2.50928 9 2.50928C5.37563 2.50928 2.4375 5.44741 2.4375 9.07178C2.4375 10.884 3.17203 12.5246 4.35961 13.7122L2.4375 15.6343H9Z"
                                                    stroke="" stroke-width="1.5" stroke-linejoin="round" />
                                            </svg>
                                            <span x-text="task.comments"></span>
                                        </span>
                                    </template>

                                    <!-- Links -->
                                    <template x-if="task.links">
                                        <span
                                            class="flex cursor-pointer items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
                                            <svg class="fill-current" width="16" height="16" viewBox="0 0 16 16"
                                                fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M6.88066 3.10905C8.54039 1.44932 11.2313 1.44933 12.8911 3.10906C14.5508 4.76878 14.5508 7.45973 12.8911 9.11946L12.0657 9.94479L11.0051 8.88413L11.8304 8.0588C12.9043 6.98486 12.9043 5.24366 11.8304 4.16972C10.7565 3.09577 9.01526 3.09577 7.94132 4.16971L7.11599 4.99504L6.05533 3.93438L6.88066 3.10905ZM8.88376 11.0055L9.94442 12.0661L9.11983 12.8907C7.4601 14.5504 4.76915 14.5504 3.10942 12.8907C1.44969 11.231 1.44969 8.54002 3.10942 6.88029L3.93401 6.0557L4.99467 7.11636L4.17008 7.94095C3.09614 9.01489 3.09614 10.7561 4.17008 11.83C5.24402 12.904 6.98522 12.904 8.05917 11.83L8.88376 11.0055ZM9.94458 7.11599C10.2375 6.8231 10.2375 6.34823 9.94458 6.05533C9.65169 5.76244 9.17682 5.76244 8.88392 6.05533L6.0555 8.88376C5.7626 9.17665 5.7626 9.65153 6.0555 9.94442C6.34839 10.2373 6.82326 10.2373 7.11616 9.94442L9.94458 7.11599Z"
                                                    fill="" />
                                            </svg>
                                            <span x-text="task.links"></span>
                                        </span>
                                    </template>
                                </div>

                                <!-- Category Badge -->
                                <template x-if="task.category">
                                    <span :class="task.categoryClass"
                                        class="text-theme-xs mt-3 inline-flex rounded-full px-2 py-0.5 font-medium"
                                        x-text="task.category"></span>
                                </template>
                            </div>

                            <!-- User Avatar -->
                            <template x-if="task.user">
                                <div
                                    class="h-6 w-full max-w-6 overflow-hidden rounded-full border-[0.5px] border-gray-200 dark:border-gray-800">
                                    <img :src="task.user" alt="user" />
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
    <!-- Task wrapper End -->
</div>

<script>
    function kanbanBoard() {
        return {
            draggingTaskId: null,
            draggedFromLaneId: null,

            lanes: [{
                    id: 'todo',
                    name: 'To Do',
                    badgeClass: 'bg-gray-100 text-gray-700 dark:bg-white/[0.03] dark:text-white/80',
                    tasks: [{
                            id: 1,
                            title: 'Finish user onboarding',
                            date: 'Tomorrow',
                            comments: 1,
                            user: './images/user/user-01.jpg'
                        },
                        {
                            id: 2,
                            title: 'Solve the Dribbble prioritisation issue with the team',
                            date: 'Jan 8, 2027',
                            comments: 2,
                            links: 1,
                            category: 'Marketing',
                            categoryClass: 'bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400',
                            user: './images/user/user-07.jpg'
                        },
                        {
                            id: 3,
                            title: 'Change license and remove products',
                            date: 'Jan 8, 2027',
                            category: 'Dev',
                            categoryClass: 'bg-gray-100 text-gray-700 dark:bg-white/[0.03] dark:text-white/80',
                            user: './images/user/user-08.jpg'
                        }
                    ]
                },
                {
                    id: 'in-progress',
                    name: 'In Progress',
                    badgeClass: 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 text-orange-400',
                    tasks: [{
                            id: 4,
                            title: 'Work In Progress (WIP) Dashboard',
                            date: 'Today',
                            comments: 1,
                            user: './images/user/user-09.jpg'
                        },
                        {
                            id: 5,
                            title: 'Kanban Flow Manager',
                            date: 'Feb 12, 2027',
                            comments: 8,
                            links: 2,
                            category: 'Template',
                            categoryClass: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
                            user: './images/user/user-10.jpg'
                        },
                        {
                            id: 6,
                            title: 'Product Update - Q4 2024',
                            description: 'Dedicated form for a category of users that will perform actions.',
                            image: './images/task/task.png',
                            date: 'Feb 12, 2027',
                            comments: 8,
                            user: './images/user/user-11.jpg'
                        },
                        {
                            id: 7,
                            title: 'Make figbot send comment when ticket is auto-moved back to inbox',
                            date: 'Mar 08, 2027',
                            comments: 1,
                            user: './images/user/user-12.jpg'
                        }
                    ]
                },
                {
                    id: 'completed',
                    name: 'Completed',
                    badgeClass: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
                    tasks: [{
                            id: 8,
                            title: 'Manage internal feedback',
                            date: 'Tomorrow',
                            comments: 1,
                            user: './images/user/user-13.jpg'
                        },
                        {
                            id: 9,
                            title: 'Do some projects on React Native with Flutter',
                            date: 'Jan 8, 2027',
                            category: 'Development',
                            categoryClass: 'bg-orange-400/10 text-orange-400',
                            user: './images/user/user-14.jpg'
                        },
                        {
                            id: 10,
                            title: 'Design marketing assets',
                            date: 'Jan 8, 2027',
                            comments: 2,
                            links: 1,
                            category: 'Marketing',
                            categoryClass: 'bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400',
                            user: './images/user/user-15.jpg'
                        },
                        {
                            id: 11,
                            title: 'Kanban Flow Manager',
                            date: 'Feb 12, 2027',
                            comments: 8,
                            category: 'Template',
                            categoryClass: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
                            user: './images/user/user-16.jpg'
                        }
                    ]
                }
            ],

            handleDragStart(event, taskId, laneId) {
                this.draggingTaskId = taskId;
                this.draggedFromLaneId = laneId;
                event.target.classList.add('is-dragging');
            },

            handleDragEnd(event) {
                event.target.classList.remove('is-dragging');
                this.draggingTaskId = null;
                this.draggedFromLaneId = null;
            },

            handleDragOver(event, laneId) {
                event.preventDefault();

                if (!this.draggingTaskId) return;

                const lane = this.lanes.find(l => l.id === laneId);
                const fromLane = this.lanes.find(l => l.id === this.draggedFromLaneId);

                if (!lane || !fromLane) return;

                // Find the task being dragged
                const taskIndex = fromLane.tasks.findIndex(t => t.id === this.draggingTaskId);
                if (taskIndex === -1) return;

                const task = fromLane.tasks[taskIndex];

                // Remove from old lane if different
                if (laneId !== this.draggedFromLaneId) {
                    fromLane.tasks.splice(taskIndex, 1);
                    lane.tasks.push(task);
                    this.draggedFromLaneId = laneId;
                }
            },

            handleDrop(event, laneId) {
                event.preventDefault();
            },

            clearLane(laneId) {
                const lane = this.lanes.find(l => l.id === laneId);
                if (lane && confirm(`Are you sure you want to clear all tasks from ${lane.name}?`)) {
                    lane.tasks = [];
                }
            }
        }
    }
</script>
