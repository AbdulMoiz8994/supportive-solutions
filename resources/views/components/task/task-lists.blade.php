@php
    $columns = [
        [
            'title' => 'To Do',
            'badgeClass' => 'bg-gray-100 text-gray-700 dark:bg-white/[0.03] dark:text-white/80',
            'tasks' => [
                [
                    'id' => 1,
                    'title' => 'Finish user onboarding',
                    'date' => 'Tomorrow',
                    'comments' => 1,
                    'user' => '/images/user/user-01.jpg',
                ],
                [
                    'id' => 2,
                    'title' => 'Solve the Dribbble prioritisation issue with the team',
                    'date' => 'Jan 8, 2027',
                    'comments' => 2,
                    'attachments' => 1,
                    'tag' => ['text' => 'Marketing', 'color' => 'brand'],
                    'user' => '/images/user/user-07.jpg',
                ],
                [
                    'id' => 3,
                    'title' => 'Change license and remove products',
                    'date' => 'Jan 8, 2027',
                    'tag' => ['text' => 'Dev', 'color' => 'gray'],
                    'user' => '/images/user/user-08.jpg',
                ],
            ],
        ],
        [
            'title' => 'In Progress',
            'badgeClass' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-orange-400',
            'tasks' => [
                [
                    'id' => 4,
                    'title' => 'Work In Progress (WIP) Dashboard',
                    'date' => 'Today',
                    'comments' => 1,
                    'user' => '/images/user/user-09.jpg',
                ],
                [
                    'id' => 5,
                    'title' => 'Kanban Flow Manager',
                    'date' => 'Feb 12, 2027',
                    'comments' => 8,
                    'attachments' => 2,
                    'tag' => ['text' => 'Template', 'color' => 'success'],
                    'user' => '/images/user/user-10.jpg',
                ],
                [
                    'id' => 6,
                    'title' => 'Product Update - Q4 2024',
                    'date' => 'Feb 12, 2027',
                    'comments' => 8,
                    'description' => 'Dedicated form for a category of users that will perform actions.',
                    'image' => '/images/task/task.png',
                    'user' => '/images/user/user-11.jpg',
                ],
                [
                    'id' => 7,
                    'title' => 'Make figbot send comment when ticket is auto-moved back to inbox',
                    'date' => 'Mar 08, 2027',
                    'comments' => 1,
                    'user' => '/images/user/user-12.jpg',
                ],
            ],
        ],
        [
            'title' => 'Completed',
            'badgeClass' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
            'tasks' => [
                [
                    'id' => 8,
                    'title' => 'Manage internal feedback',
                    'date' => 'Tomorrow',
                    'comments' => 1,
                    'user' => '/images/user/user-13.jpg',
                ],
                [
                    'id' => 9,
                    'title' => 'Do some projects on React Native with Flutter',
                    'date' => 'Jan 8, 2027',
                    'tag' => ['text' => 'Development', 'color' => 'orange'],
                    'user' => '/images/user/user-14.jpg',
                ],
                [
                    'id' => 10,
                    'title' => 'Design marketing assets',
                    'date' => 'Jan 8, 2027',
                    'comments' => 2,
                    'attachments' => 1,
                    'tag' => ['text' => 'Marketing', 'color' => 'brand'],
                    'user' => '/images/user/user-15.jpg',
                ],
                [
                    'id' => 11,
                    'title' => 'Kanban Flow Manager',
                    'date' => 'Feb 12, 2027',
                    'comments' => 8,
                    'tag' => ['text' => 'Template', 'color' => 'success'],
                    'user' => '/images/user/user-16.jpg',
                ],
            ],
        ],
    ];

    // Calculate task counts
    $allTasksCount = collect($columns)->sum(fn($col) => count($col['tasks']));
    $todoCount = count($columns[0]['tasks']);
    $inProgressCount = count($columns[1]['tasks']);
    $completedCount = count($columns[2]['tasks']);
@endphp

<div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <x-task.task-header :allTasksCount="$allTasksCount" :todoCount="$todoCount" :inProgressCount="$inProgressCount" :completedCount="$completedCount" />

    <div class="p-4 space-y-8 border-t border-gray-200 mt-7 dark:border-gray-800 sm:mt-0 xl:p-6" x-data="kanbanBoard()">

        @foreach ($columns as $columnIndex => $column)
            <div class="flex flex-col gap-4 swim-lane" x-data="{
                columnTitle: '{{ $column['title'] }}',
                tasks: @js($column['tasks'])
            }">

                <div class="flex items-center justify-between mb-2">
                    <h3 class="flex items-center gap-3 text-base font-medium text-gray-800 dark:text-white/90">
                        {{ $column['title'] }}
                        <span
                            class="inline-flex rounded-full px-2 py-0.5 text-theme-xs font-medium {{ $column['badgeClass'] }}">
                            <span x-text="tasks.length"></span>
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
                        <div x-show="openDropDown" @click.outside="openDropDown = false"
                            class="absolute right-0 top-full z-40 w-[140px] space-y-1 rounded-2xl border border-gray-200 bg-white p-2 shadow-theme-md dark:border-gray-800 dark:bg-gray-dark">
                            <button
                                class="flex w-full px-3 py-2 font-medium text-left text-gray-500 rounded-lg text-theme-xs hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">
                                Edit
                            </button>
                            <button
                                class="flex w-full px-3 py-2 font-medium text-left text-gray-500 rounded-lg text-theme-xs hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">
                                Delete
                            </button>
                            <button
                                class="flex w-full px-3 py-2 font-medium text-left text-gray-500 rounded-lg text-theme-xs hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">
                                Clear All
                            </button>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 kanban-column min-h-[100px]" data-column="{{ $column['title'] }}"
                    @drop="handleDrop($event, '{{ $column['title'] }}')" @dragover.prevent="handleDragOver($event)"
                    @dragenter.prevent>

                    <template x-for="(task, taskIndex) in tasks" :key="task.id">
                        <div draggable="true" :data-task-id="task.id" :data-task-index="taskIndex"
                            @dragstart="handleDragStart($event, task, '{{ $column['title'] }}', taskIndex)"
                            @dragend="handleDragEnd($event)"
                            @dragover.prevent="handleDragOverTask($event, '{{ $column['title'] }}', taskIndex)"
                            class="p-5 bg-white border border-gray-200 cursor-move task rounded-xl shadow-theme-sm dark:border-gray-800 dark:bg-white/5 hover:shadow-lg transition-all">

                            <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                                <div class="flex items-start w-full gap-4">
                                    <span class="text-gray-400 cursor-grab active:cursor-grabbing">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 6h16M4 12h16M4 18h16" />
                                        </svg>
                                    </span>

                                    <label :for="'taskCheckbox' + task.id" class="w-full cursor-pointer">
                                        <div class="relative flex items-start">
                                            <input :id="'taskCheckbox' + task.id" type="checkbox"
                                                class="sr-only taskCheckbox" @change="toggleTaskComplete(task.id)" />
                                            <div
                                                class="flex items-center justify-center w-full h-5 mr-3 border border-gray-300 rounded-md box max-w-5 dark:border-gray-700">
                                                <span class="opacity-0 checkmark">
                                                    <svg width="14" height="14" viewBox="0 0 14 14"
                                                        fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M11.6668 3.5L5.25016 9.91667L2.3335 7" stroke="white"
                                                            stroke-width="1.94437" stroke-linecap="round"
                                                            stroke-linejoin="round" />
                                                    </svg>
                                                </span>
                                            </div>
                                            <p class="-mt-0.5 text-base text-gray-800 dark:text-white/90"
                                                x-text="task.title"></p>
                                        </div>
                                    </label>
                                </div>

                                <div
                                    class="flex flex-col-reverse items-start justify-end w-full gap-3 xl:flex-row xl:items-center xl:gap-5">
                                    <span x-show="task.tag" :class="getTagClass(task.tag?.color)"
                                        class="inline-flex rounded-full px-2 py-0.5 text-theme-xs font-medium"
                                        x-text="task.tag?.text">
                                    </span>

                                    <div
                                        class="flex items-center justify-between w-full gap-5 xl:w-auto xl:justify-normal">
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="flex items-center gap-1 text-sm text-gray-500 cursor-pointer dark:text-gray-400">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span x-text="task.date"></span>
                                            </span>

                                            <span x-show="task.comments"
                                                class="flex items-center gap-1 text-sm text-gray-500 cursor-pointer dark:text-gray-400">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                                </svg>
                                                <span x-text="task.comments"></span>
                                            </span>

                                            <span x-show="task.attachments"
                                                class="flex items-center gap-1 text-sm text-gray-500 cursor-pointer dark:text-gray-400">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                                </svg>
                                                <span x-text="task.attachments"></span>
                                            </span>
                                        </div>

                                        <div x-show="task.user"
                                            class="h-6 w-full max-w-6 overflow-hidden rounded-full border-[0.5px] border-gray-200 dark:border-gray-800">
                                            <img :src="task.user" :alt="task.title" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Empty state for empty columns -->
                    <div x-show="tasks.length === 0"
                        class="p-8 text-center text-gray-400 border-2 border-dashed border-gray-200 rounded-xl dark:border-gray-700">
                        Drop tasks here
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
    function kanbanBoard() {
        return {
            draggedTask: null,
            draggedTaskIndex: null,
            sourceColumn: null,
            dropTargetColumn: null,
            dropTargetIndex: null,

            handleDragStart(event, task, columnTitle, taskIndex) {
                this.draggedTask = task;
                this.draggedTaskIndex = taskIndex;
                this.sourceColumn = columnTitle;
                event.dataTransfer.effectAllowed = 'move';
                event.target.classList.add('opacity-50', 'scale-95');
            },

            handleDragEnd(event) {
                event.target.classList.remove('opacity-50', 'scale-95');
                // Clear drop indicators
                document.querySelectorAll('.task').forEach(el => {
                    el.classList.remove('border-t-2', 'border-b-2', 'border-brand-500');
                });
            },

            handleDragOver(event) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
            },

            handleDragOverTask(event, columnTitle, taskIndex) {
                event.preventDefault();
                event.stopPropagation();

                if (!this.draggedTask) return;

                // Remove previous indicators
                document.querySelectorAll('.task').forEach(el => {
                    el.classList.remove('border-t-2', 'border-b-2', 'border-brand-500');
                });

                // Get mouse position relative to the task
                const rect = event.currentTarget.getBoundingClientRect();
                const mouseY = event.clientY;
                const middle = rect.top + rect.height / 2;

                // Determine insert position
                let insertBefore = mouseY < middle;

                // Calculate the actual drop index
                if (insertBefore) {
                    this.dropTargetColumn = columnTitle;
                    this.dropTargetIndex = taskIndex;
                    event.currentTarget.classList.add('border-t-2', 'border-brand-500');
                } else {
                    this.dropTargetColumn = columnTitle;
                    this.dropTargetIndex = taskIndex + 1;
                    event.currentTarget.classList.add('border-b-2', 'border-brand-500');
                }

                // If same column and same position, hide indicator
                if (this.sourceColumn === columnTitle) {
                    const samePosition = (this.dropTargetIndex === this.draggedTaskIndex) ||
                        (this.dropTargetIndex === this.draggedTaskIndex + 1);
                    if (samePosition) {
                        document.querySelectorAll('.task').forEach(el => {
                            el.classList.remove('border-t-2', 'border-b-2', 'border-brand-500');
                        });
                    }
                }
            },

            handleDrop(event, targetColumnTitle) {
                event.preventDefault();
                event.stopPropagation();

                if (!this.draggedTask) return;

                // Use stored drop target if available, otherwise use column
                const actualTargetColumn = this.dropTargetColumn || targetColumnTitle;
                const actualTargetIndex = this.dropTargetIndex;

                // Get column elements
                const sourceColumnEl = document.querySelector(`[data-column="${this.sourceColumn}"]`);
                const targetColumnEl = document.querySelector(`[data-column="${actualTargetColumn}"]`);

                if (!sourceColumnEl || !targetColumnEl) return;

                // Get Alpine data
                const sourceData = Alpine.$data(sourceColumnEl.parentElement);
                const targetData = Alpine.$data(targetColumnEl.parentElement);

                // Same column reordering
                if (this.sourceColumn === actualTargetColumn) {
                    if (actualTargetIndex !== null && actualTargetIndex !== this.draggedTaskIndex &&
                        actualTargetIndex !== this.draggedTaskIndex + 1) {
                        // Remove task from source
                        const [movedTask] = sourceData.tasks.splice(this.draggedTaskIndex, 1);

                        // Calculate correct insert position after removal
                        let finalIndex = actualTargetIndex;
                        if (this.draggedTaskIndex < actualTargetIndex) {
                            finalIndex = actualTargetIndex - 1;
                        }

                        // Insert at new position
                        sourceData.tasks.splice(finalIndex, 0, movedTask);
                    }
                } else {
                    // Moving between columns
                    const [movedTask] = sourceData.tasks.splice(this.draggedTaskIndex, 1);

                    // Add to target
                    if (actualTargetIndex !== null) {
                        targetData.tasks.splice(actualTargetIndex, 0, movedTask);
                    } else {
                        targetData.tasks.push(movedTask);
                    }
                }

                // Clear drop indicators
                document.querySelectorAll('.task').forEach(el => {
                    el.classList.remove('border-t-2', 'border-b-2', 'border-brand-500');
                });

                // Clear state
                this.draggedTask = null;
                this.draggedTaskIndex = null;
                this.sourceColumn = null;
                this.dropTargetColumn = null;
                this.dropTargetIndex = null;

                // Save to backend
                this.saveBoard();
            },

            toggleTaskComplete(taskId) {
                console.log('Toggle task:', taskId);
                // Implement task completion logic
            },

            getTagClass(color) {
                const baseClasses = 'px-2 py-0.5 text-xs font-medium';
                switch (color) {
                    case 'brand':
                        return `${baseClasses} bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400`;
                    case 'gray':
                        return `${baseClasses} bg-gray-100 text-gray-700 dark:bg-white/[0.03] dark:text-white/80`;
                    case 'success':
                        return `${baseClasses} bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500`;
                    case 'orange':
                        return `${baseClasses} bg-orange-400/10 text-orange-400`;
                    default:
                        return baseClasses;
                }
            },

            saveBoard() {
                const boardData = {
                    columns: []
                };

                document.querySelectorAll('.kanban-column').forEach(column => {
                    const columnData = Alpine.$data(column.parentElement);
                    boardData.columns.push({
                        title: columnData.columnTitle,
                        tasks: columnData.tasks
                    });
                });

                console.log('Board saved:', boardData);
            }
        }
    }
</script>
