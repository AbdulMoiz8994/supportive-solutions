@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="tasksPage(@js([
    'tasks' => $tasks,
    'board' => $board,
    'boardStatuses' => $boardStatuses,
    'manageBoardStatuses' => $manageBoardStatuses,
    'canManageTasks' => $canManageTasks,
    'counters' => $counters,
    'assignees' => $assignees,
    'view' => $view,
    'csrfToken' => $csrfToken,
]))" x-init="init()">

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-[28px] font-extrabold text-[#0f172a] tracking-tight leading-tight">Tasks</h1>
            <p class="text-[13px] text-[#64748b] mt-1.5">Shared to-do list for staff and AI agents — drag cards on the board to change status.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('tasks', array_merge(request()->query(), ['view' => 'list'])) }}"
               class="rounded-full px-3.5 py-1.5 text-[12.5px] font-semibold {{ $view === 'list' ? 'bg-[#2563eb] text-white' : 'text-[#64748b] hover:bg-[#eef4ff]' }}">List</a>
            <a href="{{ route('tasks', array_merge(request()->query(), ['view' => 'board'])) }}"
               class="rounded-full px-3.5 py-1.5 text-[12.5px] font-semibold {{ $view === 'board' ? 'bg-[#2563eb] text-white' : 'text-[#64748b] hover:bg-[#eef4ff]' }}">Board</a>
            @if($canManageTasks && $view === 'board')
                <x-ui.btn variant="outline" size="sm" type="button" data-testid="manage-statuses-btn" @click="boardStatusesOpen = true">Manage statuses</x-ui.btn>
            @endif
            @if($canManageTasks)
                <x-ui.btn variant="primary" size="sm" type="button" data-testid="new-task-btn" @click="newTaskOpen = true">+ New Task</x-ui.btn>
            @endif
        </div>
    </div>

    @if(session('success'))
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    @endif

    <div x-show="toast" x-cloak x-transition
         class="rounded-xl border border-[#d1fadf] bg-[#ecfdf3] px-4 py-2.5 text-sm font-semibold text-[#067647]"
         x-text="toast"></div>

    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3">
        @foreach($counters as $counter)
            <a href="{{ route('tasks', array_merge(request()->except('status'), ['view' => $view], $counter['filter'] ? ['status' => $counter['filter']] : [])) }}"
               class="rounded-xl border border-[#e2e8f0] bg-white px-3.5 py-3 hover:border-[#2563eb] transition-colors">
                <div class="text-[11.5px] text-[#64748b] mb-1">{{ $counter['label'] }}</div>
                <div class="text-[20px] font-bold leading-tight {{ ($counter['tone'] ?? '') === 'danger' ? 'text-[#b91c1c]' : 'text-[#0f172a]' }}">{{ $counter['value'] }}</div>
            </a>
        @endforeach
    </div>

    <form method="GET" class="flex flex-wrap gap-2 items-center">
        <input type="hidden" name="view" value="{{ $view }}">
        <select name="status" onchange="this.form.submit()" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
            @foreach($statusOptions as $opt)
                <option value="{{ $opt['value'] }}" @selected(($filters['status'] ?? '') === $opt['value'])>{{ $opt['label'] }}</option>
            @endforeach
        </select>
        <select name="priority" onchange="this.form.submit()" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
            <option value="">All priorities</option>
            @foreach($priorityOptions as $opt)
                <option value="{{ $opt['value'] }}" @selected(($filters['priority'] ?? '') === $opt['value'])>{{ $opt['label'] }}</option>
            @endforeach
        </select>
        <select name="assignee" onchange="this.form.submit()" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
            <option value="">All assignees</option>
            @foreach($assignees as $a)
                <option value="{{ $a['type'] }}:{{ $a['id'] }}" @selected(($filters['assignee'] ?? '') === $a['type'].':'.$a['id'])>{{ $a['name'] }}</option>
            @endforeach
        </select>
        <select name="due" onchange="this.form.submit()" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
            <option value="">Any due date</option>
            <option value="due_today" @selected(($filters['due'] ?? '') === 'due_today')>Due today</option>
            <option value="overdue" @selected(($filters['due'] ?? '') === 'overdue')>Overdue</option>
        </select>
    </form>

    @if($view === 'board')
        <div class="flex gap-4 overflow-x-auto pb-2 -mx-1 px-1">
            <template x-for="(column, columnIndex) in board" :key="column.key">
                <div class="flex-shrink-0 w-[280px] sm:w-[300px] flex flex-col rounded-2xl border border-[#e2e8f0] bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)] max-h-[calc(100vh-280px)] min-h-[320px] transition-colors"
                     data-testid="task-board-column"
                     :data-status-key="column.key"
                     :class="{
                        'ring-2 ring-[#2563eb]/40 border-[#2563eb]': dragOverColumn === column.key,
                        'ring-2 ring-[#7c3aed]/40 border-[#7c3aed]': columnDragOverIndex === columnIndex && draggingColumnIndex !== null,
                     }"
                     @dragover.prevent="onColumnDragOver(columnIndex)"
                     @drop.prevent="onColumnDrop(columnIndex)">
                    <div class="flex items-center gap-2 px-3.5 py-3 border-b border-[#eef2f9] rounded-t-2xl shrink-0"
                         :style="'background:' + (column.header_bg || '#f8fbff')">
                        <button x-show="canManageTasks"
                                type="button"
                                draggable="true"
                                @dragstart.stop="onColumnDragStart($event, columnIndex)"
                                @dragend.stop="onColumnDragEnd()"
                                class="shrink-0 text-[#94a3b8] hover:text-[#475569] cursor-grab active:cursor-grabbing text-[12px] leading-none"
                                title="Drag to reorder status">⋮⋮</button>
                        <h3 class="text-[13px] font-bold text-[#0f172a] flex-1 min-w-0 truncate" x-text="column.label"></h3>
                        <span class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 rounded-full text-[11px] font-bold shrink-0"
                              :style="'background:' + (column.badge_bg || '#f1f5f9') + '; color:' + (column.badge_text || '#475569')"
                              x-text="column.count ?? column.tasks?.length ?? 0"></span>
                    </div>
                    <div class="flex-1 overflow-y-auto p-3 space-y-2 tasks-board-scroll"
                         @dragover.prevent.stop="onDragOver(column.key)"
                         @dragleave="onDragLeave(column.key)"
                         @drop.prevent.stop="onDrop(column.key, $event)">
                        <template x-for="task in column.tasks" :key="column.key + '-' + task.id">
                            <div draggable="true"
                                 data-testid="task-board-card"
                                 :data-task-id="task.id"
                                 @click="openTaskDrawer(task.id, $event)"
                                 @dragstart.stop="onDragStart($event, task)"
                                 @dragend.stop="onDragEnd()"
                                 @dragover.prevent
                                 class="rounded-xl border border-[#e2e8f0] bg-white p-3 cursor-pointer hover:border-[#2563eb]/50 hover:shadow-sm transition-all select-none"
                                 :class="{
                                    'opacity-50 border-dashed': draggingTaskId === normalizeTaskId(task.id),
                                    'border-[#fecaca] bg-[#fef2f2]/30': task.is_overdue,
                                 }">
                                <div class="text-[13px] font-semibold text-[#0f172a] leading-snug" x-text="task.title"></div>
                                <div class="text-[11px] text-[#64748b] mt-1" x-text="task.assignee"></div>
                                <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                    <span x-show="task.is_overdue" class="text-[10px] font-bold uppercase text-[#b91c1c] bg-[#fee2e2] px-1.5 py-0.5 rounded">Overdue</span>
                                    <span x-show="(task.priority_effective || task.priority) === 'high'" class="text-[10px] font-bold uppercase text-[#c2410c]">High</span>
                                    <span class="text-[10px] text-[#94a3b8]" x-text="'Due ' + task.due_date"></span>
                                </div>
                                <a x-show="task.related_url" :href="task.related_url"
                                   class="text-[11px] font-semibold text-[#2563eb] mt-2 inline-block"
                                   @click.stop>Open →</a>
                            </div>
                        </template>
                        <div x-show="!column.tasks?.length"
                             class="rounded-xl border border-dashed border-[#e2e8f0] bg-[#f8fbff] px-3 py-8 text-center text-[12px] text-[#94a3b8]">
                            Drop tasks here
                        </div>
                    </div>
                </div>
            </template>
        </div>
        <p class="text-[11.5px] text-[#94a3b8]">Tip: drag a card to another status to update it instantly.@if($canManageTasks) Drag a status header (⋮⋮) to reorder columns.@endif</p>
    @else
        <div class="rounded-2xl border border-[#e6eef9] bg-white overflow-hidden">
            <div class="divide-y divide-[#f1f5f9]">
                @forelse($tasks as $task)
                    @include('pages.tasks.partials.task-row', ['task' => $task, 'boardStatuses' => $boardStatuses])
                @empty
                    <div class="p-10 text-center text-[#64748b] text-[13px]">No tasks yet. Create one or wait for the system to generate them.</div>
                @endforelse
            </div>
        </div>
    @endif

    @include('pages.tasks.partials.new-task-modal')
    @include('pages.tasks.partials.task-detail-drawer')
    @if($canManageTasks)
        @include('pages.tasks.partials.board-statuses-modal')
    @endif
</div>

<style>
    .tasks-board-scroll {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }
    .tasks-board-scroll::-webkit-scrollbar {
        width: 6px;
    }
    .tasks-board-scroll::-webkit-scrollbar-track {
        background: transparent;
    }
    .tasks-board-scroll::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 999px;
    }
    .tasks-board-scroll::-webkit-scrollbar-thumb:hover {
        background-color: #94a3b8;
    }
</style>

<script>
function tasksPage(initial) {
    return {
        tasks: initial.tasks,
        board: initial.board,
        boardStatuses: initial.boardStatuses,
        manageBoardStatuses: initial.manageBoardStatuses ?? [],
        canManageTasks: initial.canManageTasks ?? false,
        counters: initial.counters,
        assignees: initial.assignees,
        view: initial.view ?? 'list',
        csrfToken: initial.csrfToken,
        newTaskOpen: false,
        boardStatusesOpen: false,
        taskDrawerOpen: false,
        taskDrawerLoading: false,
        taskDrawerSaving: false,
        taskDrawerEditing: false,
        taskDrawerError: null,
        activeTask: null,
        drawerPhase: null, // human_open | agent_submit | agent_awaiting | completed
        taskDrawerForm: {},
        taskComments: [],
        taskCommentsLoading: false,
        taskCommentSaving: false,
        taskApprovalSubmitting: false,
        newCommentBody: '',
        suppressTaskClick: false,
        drawerDueDatePicker: null,
        toast: null,
        draggingTaskId: null,
        dragOverColumn: null,
        isDropping: false,
        draggingColumnIndex: null,
        columnDragOverIndex: null,
        isColumnDropping: false,
        draggingStatusIndex: null,
        statusDragOverIndex: null,
        isStatusDropping: false,
        toastTimeout: null,
        dueDatePicker: null,

        init() {
            this.$watch('newTaskOpen', (open) => {
                if (open) {
                    this.$nextTick(() => this.initDueDatePicker());
                } else {
                    this.destroyDueDatePicker();
                    this.resetTaskForm();
                }
            });

            this.$watch('taskDrawerEditing', (editing) => {
                if (editing) {
                    this.$nextTick(() => this.initDrawerDueDatePicker());
                } else {
                    this.destroyDrawerDueDatePicker();
                }
            });
        },

        showToast(message, duration = 2500) {
            this.toast = message;
            if (this.toastTimeout) {
                clearTimeout(this.toastTimeout);
            }
            this.toastTimeout = setTimeout(() => {
                this.toast = null;
                this.toastTimeout = null;
            }, duration);
        },

        initDueDatePicker() {
            const input = document.getElementById('task-due-date');
            if (!input || typeof flatpickr === 'undefined') return;

            this.destroyDueDatePicker();

            this.dueDatePicker = flatpickr(input, {
                disableMobile: true,
                dateFormat: 'Y-m-d',
                static: false,
                allowInput: true,
                onChange: (_dates, dateStr) => {
                    this.form.due_date = dateStr;
                },
            });
        },

        destroyDueDatePicker() {
            if (this.dueDatePicker) {
                this.dueDatePicker.destroy();
                this.dueDatePicker = null;
            }
        },

        clearDueDatePicker() {
            if (this.dueDatePicker) {
                this.dueDatePicker.clear();
            }
        },

        resetTaskForm() {
            this.form = {
                title: '',
                description: '',
                priority: 'medium',
                due_date: '',
                assignee_type: 'user',
                assignee_user_id: '',
                assignee_agent_id: '',
                client_id: '',
                employee_id: '',
            };
            this.clearDueDatePicker();
        },

        normalizeTaskId(value) {
            const id = Number(value);
            return Number.isFinite(id) && id > 0 ? id : null;
        },

        onDragStart(event, task) {
            const id = this.normalizeTaskId(task.id);
            if (!id) return;

            this.suppressTaskClick = true;
            this.draggingTaskId = id;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('application/x-task-id', String(id));
            event.dataTransfer.setData('text/plain', String(id));
        },

        onColumnDragStart(event, columnIndex) {
            this.draggingColumnIndex = columnIndex;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('application/x-column-index', String(columnIndex));
        },

        onColumnDragEnd() {
            if (this.isColumnDropping) return;
            this.draggingColumnIndex = null;
            this.columnDragOverIndex = null;
        },

        onColumnDragOver(columnIndex) {
            if (this.draggingColumnIndex === null) return;
            this.columnDragOverIndex = columnIndex;
        },

        async onColumnDrop(targetIndex) {
            if (this.draggingColumnIndex === null || this.draggingTaskId !== null) return;

            this.isColumnDropping = true;
            const fromIndex = this.draggingColumnIndex;
            this.draggingColumnIndex = null;
            this.columnDragOverIndex = null;

            if (fromIndex === targetIndex) {
                this.isColumnDropping = false;
                return;
            }

            this.reorderBoardLocally(fromIndex, targetIndex);
            await this.persistStatusOrder();
            this.isColumnDropping = false;
        },

        onStatusDragStart(event, statusIndex) {
            this.draggingStatusIndex = statusIndex;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('application/x-status-index', String(statusIndex));
        },

        onStatusDragEnd() {
            if (this.isStatusDropping) return;
            this.draggingStatusIndex = null;
            this.statusDragOverIndex = null;
        },

        onStatusDragOver(statusIndex) {
            if (this.draggingStatusIndex === null) return;
            this.statusDragOverIndex = statusIndex;
        },

        async onStatusDrop(targetIndex) {
            if (this.draggingStatusIndex === null) return;

            this.isStatusDropping = true;
            const fromIndex = this.draggingStatusIndex;
            this.draggingStatusIndex = null;
            this.statusDragOverIndex = null;

            if (fromIndex === targetIndex) {
                this.isStatusDropping = false;
                return;
            }

            this.reorderStatusesLocally(fromIndex, targetIndex);
            await this.persistStatusOrder();
            this.isStatusDropping = false;
        },

        reorderBoardLocally(fromIndex, toIndex) {
            const columns = [...this.board];
            const [moved] = columns.splice(fromIndex, 1);
            columns.splice(toIndex, 0, moved);
            this.board = columns;

            if ((this.manageBoardStatuses || []).length === this.board.length) {
                const statuses = [...this.manageBoardStatuses];
                const [movedStatus] = statuses.splice(fromIndex, 1);
                statuses.splice(toIndex, 0, movedStatus);
                this.manageBoardStatuses = statuses;
            }
        },

        reorderStatusesLocally(fromIndex, toIndex) {
            const statuses = [...this.manageBoardStatuses];
            const [moved] = statuses.splice(fromIndex, 1);
            statuses.splice(toIndex, 0, moved);
            this.manageBoardStatuses = statuses;
            this.reorderBoardLocally(fromIndex, toIndex);
        },

        statusOrderIds() {
            if ((this.manageBoardStatuses || []).length) {
                return this.manageBoardStatuses.map(s => s.id);
            }

            return (this.board || []).map(c => c.id).filter(Boolean);
        },

        async persistStatusOrder() {
            const order = this.statusOrderIds();
            if (!order.length) return;

            const previousBoard = [...this.board];
            const previousStatuses = [...this.manageBoardStatuses];

            const form = new FormData();
            order.forEach(id => form.append('order[]', id));

            try {
                const res = await fetch('{{ route('tasks.board-statuses.reorder') }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: form,
                });
                const data = await res.json();

                if (!res.ok || !data.ok) {
                    throw new Error(data.message || 'Could not save status order.');
                }

                this.applyBoardStructure(data);
            } catch (error) {
                this.board = previousBoard;
                this.manageBoardStatuses = previousStatuses;
                this.toast = error.message || 'Could not save status order.';
            }
        },

        releaseTaskClickSuppression() {
            setTimeout(() => { this.suppressTaskClick = false; }, 100);
        },

        onDragEnd() {
            if (this.isDropping) return;
            this.dragOverColumn = null;
            this.draggingTaskId = null;
            this.releaseTaskClickSuppression();
        },

        onDragOver(columnKey) {
            this.dragOverColumn = columnKey;
        },

        onDragLeave(columnKey) {
            if (this.dragOverColumn === columnKey) {
                this.dragOverColumn = null;
            }
        },

        async onDrop(columnKey, event) {
            this.isDropping = true;

            try {
                const rawId = event?.dataTransfer?.getData('application/x-task-id')
                    || event?.dataTransfer?.getData('text/plain');
                const taskId = this.normalizeTaskId(rawId) ?? this.normalizeTaskId(this.draggingTaskId);

                this.dragOverColumn = null;
                this.draggingTaskId = null;

                if (!taskId) return;

                const task = this.findTask(taskId);
                if (!task || task.board_status === columnKey) return;

                await this.moveTask(taskId, columnKey);
            } finally {
                this.isDropping = false;
                this.releaseTaskClickSuppression();
            }
        },

        findTask(taskId) {
            const id = this.normalizeTaskId(taskId);
            if (!id) return null;

            for (const column of this.board) {
                const match = (column.tasks || []).find(t => this.normalizeTaskId(t.id) === id);
                if (match) return match;
            }

            return this.tasks.find(t => this.normalizeTaskId(t.id) === id) ?? null;
        },

        moveTaskToColumn(taskId, fromStatus, toStatus) {
            const id = this.normalizeTaskId(taskId);
            if (!id || fromStatus === toStatus) return false;

            const sourceCol = this.board.find(c => c.key === fromStatus);
            const targetCol = this.board.find(c => c.key === toStatus);
            if (!sourceCol || !targetCol || !Array.isArray(sourceCol.tasks)) return false;

            const idx = sourceCol.tasks.findIndex(t => this.normalizeTaskId(t.id) === id);
            if (idx === -1) return false;

            const [moved] = sourceCol.tasks.splice(idx, 1);
            moved.board_status = toStatus;
            sourceCol.count = sourceCol.tasks.length;

            if (!Array.isArray(targetCol.tasks)) {
                targetCol.tasks = [];
            }
            targetCol.tasks.push(moved);
            targetCol.count = targetCol.tasks.length;

            return true;
        },

        syncTaskFields(taskId, updated) {
            const id = this.normalizeTaskId(taskId);
            const normalized = this.normalizeTaskPayload(updated);
            if (!id || !normalized) return;

            for (const column of this.board) {
                const task = (column.tasks || []).find(t => this.normalizeTaskId(t.id) === id);
                if (task) {
                    Object.assign(task, normalized);
                    break;
                }
            }

            const listIdx = (this.tasks || []).findIndex(t => this.normalizeTaskId(t.id) === id);
            if (listIdx !== -1) {
                this.tasks[listIdx] = { ...this.tasks[listIdx], ...normalized };
            }

            if (this.activeTask && this.normalizeTaskId(this.activeTask.id) === id) {
                this.setActiveTask({ ...this.activeTask, ...normalized });
            }
        },

        normalizeTaskPayload(task) {
            if (!task || typeof task !== 'object') return null;

            const awaiting = task.awaiting_approval === true
                || task.awaiting_approval === 1
                || task.awaiting_approval === '1';

            const assigneeType = task.assignee_type
                || (task.assignee_agent_id ? 'agent' : 'user');

            const boardStatus = task.board_status
                || (task.status && task.status !== 'overdue' ? task.status : null)
                || 'todo';

            return {
                ...task,
                id: this.normalizeTaskId(task.id) ?? task.id,
                assignee_type: assigneeType,
                awaiting_approval: awaiting,
                board_status: boardStatus,
            };
        },

        setActiveTask(task) {
            this.activeTask = this.normalizeTaskPayload(task);
            this.refreshDrawerPhase();
        },

        refreshDrawerPhase() {
            if (!this.activeTask) {
                this.drawerPhase = null;
                return;
            }

            if (this.isClosedBoardStatus(this.activeTask.board_status)) {
                this.drawerPhase = 'completed';
                return;
            }

            if (this.activeTask.assignee_type === 'agent') {
                this.drawerPhase = this.activeTask.awaiting_approval
                    ? 'agent_awaiting'
                    : 'agent_submit';
                return;
            }

            this.drawerPhase = 'human_open';
        },

        isClosedBoardStatus(statusKey) {
            const key = String(statusKey || '');
            if (!key) return false;

            const fromBoard = (this.boardStatuses || []).find(s => s.key === key);
            if (fromBoard && typeof fromBoard.is_closed !== 'undefined') {
                return !!fromBoard.is_closed;
            }

            const fromManage = (this.manageBoardStatuses || []).find(s => s.key === key);
            if (fromManage && typeof fromManage.is_closed !== 'undefined') {
                return !!fromManage.is_closed;
            }

            return key === 'done';
        },

        closedStatusKeys() {
            const fromBoard = (this.boardStatuses || [])
                .filter(s => !!s.is_closed)
                .map(s => s.key);
            if (fromBoard.length) return fromBoard;

            const fromManage = (this.manageBoardStatuses || [])
                .filter(s => !!s.is_closed)
                .map(s => s.key);
            if (fromManage.length) return fromManage;

            return ['done'];
        },

        async openTaskDrawer(taskId, event) {
            if (event?.target?.closest('a, button, select, input, textarea')) return;
            if (this.suppressTaskClick || this.draggingTaskId) return;

            const id = this.normalizeTaskId(taskId);
            if (!id) return;

            this.taskDrawerOpen = true;
            this.taskDrawerLoading = true;
            this.taskDrawerEditing = false;
            this.taskDrawerError = null;
            this.activeTask = null;
            this.drawerPhase = null;
            this.taskComments = [];
            this.newCommentBody = '';

            try {
                const res = await fetch(`/tasks/${id}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await res.json();

                if (!res.ok || !data.ok) {
                    throw new Error(data.message || 'Could not load task.');
                }

                this.setActiveTask(data.task);
                this.loadTaskComments(id);
            } catch (error) {
                this.taskDrawerError = error.message || 'Could not load task.';
            } finally {
                this.taskDrawerLoading = false;
            }
        },

        async loadTaskComments(taskId) {
            const id = this.normalizeTaskId(taskId);
            if (!id) return;

            this.taskCommentsLoading = true;
            try {
                const res = await fetch(`/tasks/${id}/comments`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await res.json();
                if (res.ok && data.ok) {
                    this.taskComments = data.comments || [];
                }
            } catch {
                // Keep drawer usable even if comments fail to load.
            } finally {
                this.taskCommentsLoading = false;
            }
        },

        async addTaskComment() {
            if (!this.activeTask || this.taskCommentSaving) return;

            const body = String(this.newCommentBody || '').trim();
            if (!body) return;

            this.taskCommentSaving = true;
            const form = new FormData();
            form.append('body', body);

            try {
                const res = await fetch(`/tasks/${this.activeTask.id}/comments`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: form,
                });
                const data = await res.json();

                if (!res.ok || !data.ok) {
                    throw new Error(data.message || 'Could not add comment.');
                }

                this.taskComments = data.comments || [];
                this.newCommentBody = '';
                this.showToast(data.message || 'Comment added.');
            } catch (error) {
                this.showToast(error.message || 'Could not add comment.', 3500);
            } finally {
                this.taskCommentSaving = false;
            }
        },

        async submitTaskForApproval() {
            if (!this.activeTask || this.taskApprovalSubmitting) return;

            this.taskApprovalSubmitting = true;
            try {
                const res = await fetch(`/tasks/${this.activeTask.id}/submit-for-approval`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await res.json();

                if (!res.ok || !data.ok) {
                    throw new Error(data.message || 'Could not submit for approval.');
                }

                // Clone before board sync so we never mutate-by-reference.
                const submitted = this.normalizeTaskPayload({
                    ...(data.task || {}),
                    assignee_type: data.task?.assignee_type || 'agent',
                    awaiting_approval: true,
                    board_status: data.task?.board_status || 'in_progress',
                    completed_at: null,
                });
                this.setActiveTask(submitted);
                this.patchTaskInBoard({ ...submitted });
                this.showToast(data.message || 'Submitted for approval.');
            } catch (error) {
                this.showToast(error.message || 'Could not submit for approval.', 3500);
            } finally {
                this.taskApprovalSubmitting = false;
            }
        },

        closeTaskDrawer() {
            this.taskDrawerOpen = false;
            this.taskDrawerEditing = false;
            this.taskDrawerSaving = false;
            this.taskDrawerError = null;
            this.activeTask = null;
            this.drawerPhase = null;
            this.taskDrawerForm = {};
            this.taskComments = [];
            this.newCommentBody = '';
            this.destroyDrawerDueDatePicker();
        },

        startTaskDrawerEdit() {
            if (!this.activeTask) return;

            this.taskDrawerForm = {
                title: this.activeTask.title || '',
                description: this.activeTask.description || '',
                status: this.activeTask.board_status || this.activeTask.status,
                priority: this.activeTask.priority_stored || this.activeTask.priority || 'medium',
                due_date: this.activeTask.due_date_raw || '',
                assignee_type: this.activeTask.assignee_type || 'user',
                assignee_user_id: this.activeTask.assignee_user_id || '',
                assignee_agent_id: this.activeTask.assignee_agent_id || '',
            };

            this.taskDrawerEditing = true;

            this.$nextTick(() => {
                const select = document.getElementById('drawer-task-assignee');
                if (!select) return;

                if (this.taskDrawerForm.assignee_type === 'agent' && this.taskDrawerForm.assignee_agent_id) {
                    select.value = `agent:${this.taskDrawerForm.assignee_agent_id}`;
                } else if (this.taskDrawerForm.assignee_user_id) {
                    select.value = `user:${this.taskDrawerForm.assignee_user_id}`;
                } else {
                    select.value = '';
                }
            });
        },

        cancelTaskDrawerEdit() {
            this.taskDrawerEditing = false;
            this.taskDrawerForm = {};
            this.destroyDrawerDueDatePicker();
        },

        setDrawerAssignee(event) {
            const value = event.target.value;
            if (!value) {
                this.taskDrawerForm.assignee_type = 'user';
                this.taskDrawerForm.assignee_user_id = '';
                this.taskDrawerForm.assignee_agent_id = '';
                return;
            }

            const [type, id] = value.split(':');
            this.taskDrawerForm.assignee_type = type;
            this.taskDrawerForm.assignee_user_id = type === 'user' ? id : '';
            this.taskDrawerForm.assignee_agent_id = type === 'agent' ? id : '';
        },

        initDrawerDueDatePicker() {
            const input = document.getElementById('drawer-task-due-date');
            if (!input || typeof flatpickr === 'undefined') return;

            this.destroyDrawerDueDatePicker();

            this.drawerDueDatePicker = flatpickr(input, {
                disableMobile: true,
                dateFormat: 'Y-m-d',
                static: false,
                allowInput: true,
                defaultDate: this.taskDrawerForm.due_date || null,
                onChange: (_dates, dateStr) => {
                    this.taskDrawerForm.due_date = dateStr;
                },
            });
        },

        destroyDrawerDueDatePicker() {
            if (this.drawerDueDatePicker) {
                this.drawerDueDatePicker.destroy();
                this.drawerDueDatePicker = null;
            }
        },

        patchTaskInBoard(updated) {
            const id = this.normalizeTaskId(updated.id);
            if (!id) return;

            const previousStatus = this.findTask(id)?.board_status
                ?? (this.activeTask && this.normalizeTaskId(this.activeTask.id) === id
                    ? this.activeTask.board_status
                    : null);
            const newStatus = updated.board_status;

            if (previousStatus && newStatus && previousStatus !== newStatus) {
                this.moveTaskToColumn(id, previousStatus, newStatus);
            }

            this.syncTaskFields(id, updated);
        },

        async saveTaskDrawer() {
            if (!this.activeTask || this.taskDrawerSaving) return;

            const title = String(this.taskDrawerForm.title || '').trim();
            if (!title) {
                this.showToast('Enter a task title.', 3000);
                return;
            }

            this.taskDrawerSaving = true;
            const form = new FormData();
            form.append('title', title);
            form.append('description', this.taskDrawerForm.description || '');
            form.append('status', this.taskDrawerForm.status);
            form.append('priority', this.taskDrawerForm.priority);
            if (this.taskDrawerForm.due_date) form.append('due_date', this.taskDrawerForm.due_date);
            form.append('assignee_type', this.taskDrawerForm.assignee_type || 'user');
            if (this.taskDrawerForm.assignee_user_id) form.append('assignee_user_id', this.taskDrawerForm.assignee_user_id);
            if (this.taskDrawerForm.assignee_agent_id) form.append('assignee_agent_id', this.taskDrawerForm.assignee_agent_id);
            form.append('_method', 'PUT');

            try {
                const res = await fetch(`/tasks/${this.activeTask.id}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: form,
                });
                const data = await res.json();

                if (!res.ok || !data.ok) {
                    throw new Error(data.message || 'Could not save task.');
                }

                this.setActiveTask(data.task);
                this.taskDrawerEditing = false;
                this.taskDrawerForm = {};
                this.destroyDrawerDueDatePicker();

                if (this.view === 'board') {
                    this.patchTaskInBoard(data.task);
                }

                if (data.counters) this.counters = data.counters;
                this.showToast(data.message || 'Task updated.');

                if (this.view === 'list') {
                    setTimeout(() => window.location.reload(), 1200);
                }
            } catch (error) {
                this.showToast(error.message || 'Could not save task.', 3500);
            } finally {
                this.taskDrawerSaving = false;
            }
        },

        async moveTask(taskId, status) {
            const id = this.normalizeTaskId(taskId);
            if (!id) return;

            // Drawer is source of truth while open — board copies can lag after submit-for-approval.
            let task = null;
            if (this.activeTask && this.normalizeTaskId(this.activeTask.id) === id) {
                task = this.activeTask;
            }
            if (!task) task = this.findTask(id);
            if (!task || task.board_status === status) return;

            const closedKeys = this.closedStatusKeys();
            const isAgent = task.assignee_type === 'agent';
            const awaiting = !!task.awaiting_approval;
            if (isAgent && !awaiting && closedKeys.includes(status)) {
                this.showToast('Agent tasks must be submitted for approval before Done.', 3500);
                return;
            }

            const fromStatus = task.board_status;
            const form = new FormData();
            form.append('status', status);
            form.append('view', 'board');

            try {
                const res = await fetch(`/tasks/${id}/status`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: form,
                });

                let data;
                try {
                    data = await res.json();
                } catch {
                    throw new Error('Unexpected server response. Please refresh the page.');
                }

                if (!res.ok || !data.ok) {
                    throw new Error(data.message || 'Could not move task');
                }

                const payload = this.normalizeTaskPayload(data.task || {
                    id,
                    board_status: status,
                    status: status,
                    status_label: (this.boardStatuses || []).find(s => s.key === status)?.label || status,
                    is_overdue: false,
                    awaiting_approval: false,
                    assignee_type: task.assignee_type,
                });

                this.moveTaskToColumn(id, fromStatus, payload.board_status || status);
                this.syncTaskFields(id, payload);

                // Force drawer phase from the server payload (avoids stale panel flicker).
                if (this.activeTask && this.normalizeTaskId(this.activeTask.id) === id) {
                    this.setActiveTask(payload);
                }

                if (data.counters) this.counters = data.counters;

                this.showToast(data.message || 'Task moved.');
            } catch (error) {
                this.showToast(error.message || 'Move failed.', 3500);
            }
        },

        applyBoardStructure(data) {
            if (data.board) this.board = data.board;
            if (data.boardStatuses) this.boardStatuses = data.boardStatuses;
            if (data.manageBoardStatuses) this.manageBoardStatuses = data.manageBoardStatuses;
            if (data.counters) this.counters = data.counters;
        },

        slugifyStatusKey(label) {
            return String(label || '')
                .trim()
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '')
                .replace(/^(\d)/, 's_$1');
        },

        generateStatusKey(label) {
            let base = this.slugifyStatusKey(label);
            if (!base) return '';

            const existing = new Set((this.manageBoardStatuses || []).map(s => s.key));
            if (!existing.has(base)) return base;

            let suffix = 2;
            while (existing.has(`${base}_${suffix}`)) {
                suffix += 1;
            }

            return `${base}_${suffix}`;
        },

        statusForm: {
            label: '',
            is_closed: false,
        },

        async addBoardStatus() {
            const label = String(this.statusForm.label || '').trim();
            if (!label) {
                this.toast = 'Enter a status name.';
                return;
            }

            const key = this.generateStatusKey(label);
            const form = new FormData();
            form.append('label', label);
            form.append('key', key);
            if (this.statusForm.is_closed) form.append('is_closed', '1');

            const res = await fetch('{{ route('tasks.board-statuses.store') }}', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                body: form,
            });
            const data = await res.json();

            if (!res.ok || !data.ok) {
                this.toast = data.message || 'Could not add status.';
                return;
            }

            this.applyBoardStructure(data);
            this.manageBoardStatuses = data.manageBoardStatuses ?? this.manageBoardStatuses;
            this.statusForm = { label: '', is_closed: false };
            this.boardStatusesOpen = false;
            this.toast = data.message || 'Status added.';
        },

        async saveBoardStatus(status) {
            const form = new FormData();
            form.append('label', status.label);
            if (status.is_closed) form.append('is_closed', '1');
            form.append('_method', 'PUT');

            const res = await fetch(`/tasks/board-statuses/${status.id}`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                body: form,
            });
            const data = await res.json();

            if (!res.ok || !data.ok) {
                this.toast = data.message || 'Could not save status.';
                return;
            }

            this.applyBoardStructure(data);
            this.toast = data.message || 'Status saved.';
        },

        async deleteBoardStatus(status) {
            if (!confirm(`Remove status "${status.label}"?`)) return;

            const form = new FormData();
            form.append('_method', 'DELETE');

            const res = await fetch(`/tasks/board-statuses/${status.id}`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                body: form,
            });
            const data = await res.json();

            if (!res.ok || !data.ok) {
                this.toast = data.message || 'Could not remove status.';
                return;
            }

            this.applyBoardStructure(data);
            this.manageBoardStatuses = (this.manageBoardStatuses || []).filter(s => s.id !== status.id);
            this.toast = data.message || 'Status removed.';
        },

        async updateStatus(taskId, status) {
            await this.moveTask(taskId, status);
            if (this.toast && !this.toast.toLowerCase().includes('fail')) {
                setTimeout(() => window.location.reload(), 1500);
            }
        },

        form: {
            title: '',
            description: '',
            priority: 'medium',
            due_date: '',
            assignee_type: 'user',
            assignee_user_id: '',
            assignee_agent_id: '',
            client_id: '',
            employee_id: '',
        },

        async createTask() {
            const title = String(this.form.title || '').trim();
            if (!title) {
                this.showToast('Enter a task title.', 3000);
                return;
            }

            const form = new FormData();
            Object.entries(this.form).forEach(([k, v]) => { if (v) form.append(k, v); });
            form.append('title', title);

            try {
                const res = await fetch('{{ route('tasks.store') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: form,
                });

                let data;
                try {
                    data = await res.json();
                } catch {
                    throw new Error('Unexpected server response. Please try again.');
                }

                if (!res.ok || !data.ok) {
                    throw new Error(data.message || 'Could not create task.');
                }

                this.newTaskOpen = false;
                this.resetTaskForm();
                this.showToast(data.message || 'Task created.', 2000);

                setTimeout(() => window.location.reload(), 1500);
            } catch (error) {
                this.showToast(error.message || 'Could not create task.', 3500);
            }
        },
    };
}
</script>
@endsection
