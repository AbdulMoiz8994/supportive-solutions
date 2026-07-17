<template x-teleport="body">
    <div x-show="taskDrawerOpen" x-cloak data-testid="task-detail-drawer" class="fixed inset-0 z-[999998]">
        <div x-show="taskDrawerOpen"
             x-transition.opacity
             class="absolute inset-0 bg-[#0f172a]/40 backdrop-blur-[1px]"
             @click="closeTaskDrawer()"></div>

        <div x-show="taskDrawerOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
             class="absolute right-0 top-0 h-full w-full max-w-lg bg-white shadow-2xl flex flex-col"
             @click.stop>

            <div class="px-6 py-5 border-b border-[#eef2f9] bg-[#f8fbff] flex items-start justify-between gap-3 shrink-0">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-[#64748b]">Task details</p>
                    <h3 class="text-[17px] font-bold text-[#0f172a] leading-snug mt-1 truncate"
                        x-text="taskDrawerEditing ? 'Edit task' : (activeTask?.title || 'Task')"></h3>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <button x-show="canManageTasks && !taskDrawerEditing && activeTask && !taskDrawerLoading"
                            type="button"
                            data-testid="task-drawer-edit-btn"
                            @click="startTaskDrawerEdit()"
                            class="rounded-lg px-2.5 py-1.5 text-[12px] font-semibold text-[#2563eb] hover:bg-white border border-transparent hover:border-[#e2e8f0] transition-colors">
                        Edit
                    </button>
                    <button type="button"
                            data-testid="task-drawer-close-btn"
                            @click="closeTaskDrawer()"
                            class="w-8 h-8 rounded-lg border border-[#eef2f9] flex items-center justify-center text-[#94a3b8] hover:bg-white hover:text-[#0f172a]"
                            aria-label="Close">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-5">
                <div x-show="taskDrawerLoading" class="flex flex-col items-center justify-center py-16 text-center">
                    <svg class="w-7 h-7 text-[#2563eb] animate-spin mb-3" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                    <p class="text-[12.5px] font-semibold text-[#64748b]">Loading task…</p>
                </div>

                <div x-show="taskDrawerError && !taskDrawerLoading" x-cloak class="rounded-xl border border-[#fee4e2] bg-[#fef3f2] p-4">
                    <p class="text-[13px] font-bold text-[#b42318] mb-1">Could not load task</p>
                    <p class="text-[12px] text-[#d92d20]" x-text="taskDrawerError"></p>
                </div>

                {{-- View mode --}}
                <div x-show="activeTask && !taskDrawerLoading && !taskDrawerEditing" x-cloak class="space-y-5">
                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold bg-[#eff6ff] text-[#1d4ed8]"
                              x-text="activeTask?.status_label"></span>
                        <span x-show="activeTask?.awaiting_approval"
                              class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold uppercase bg-[#fef3c7] text-[#b45309]"
                              data-testid="task-awaiting-approval-badge">Awaiting approval</span>
                        <span x-show="activeTask?.is_overdue"
                              class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold uppercase bg-[#fee2e2] text-[#b91c1c]">Overdue</span>
                        <span x-show="(activeTask?.priority_effective || activeTask?.priority) === 'high'"
                              class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold uppercase bg-[#ffedd5] text-[#c2410c]">High priority</span>
                        <span x-show="activeTask?.priority_elevated"
                              class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold text-[#64748b] bg-[#f1f5f9]">Elevated (overdue)</span>
                        <span x-show="activeTask?.source === 'system'"
                              class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold bg-[#f1f5f9] text-[#64748b]"
                              x-text="activeTask?.source_label"></span>
                    </div>

                    <div x-show="canManageTasks && activeTask && drawerPhase && drawerPhase !== 'completed'"
                         class="space-y-2 pt-1"
                         data-testid="task-drawer-actions">

                        {{-- Agent: not yet submitted --}}
                        <div x-show="drawerPhase === 'agent_submit'" x-cloak data-testid="task-submit-approval-panel">
                            <x-ui.btn variant="outline" size="sm" type="button"
                                     @click="submitTaskForApproval()"
                                     x-bind:disabled="taskApprovalSubmitting"
                                     data-testid="task-submit-for-approval">
                                <span x-text="taskApprovalSubmitting ? 'Submitting…' : 'Submit for approval'"></span>
                            </x-ui.btn>
                            <p class="text-[11.5px] text-[#64748b] mt-1.5 leading-snug">
                                Agent tasks go to the Approval Queue — they can’t be marked Done until a human signs off.
                            </p>
                        </div>

                        {{-- Agent: submitted, waiting for human --}}
                        <div x-show="drawerPhase === 'agent_awaiting'" x-cloak
                             class="rounded-xl border border-[#fde68a] bg-[#fffbeb] px-3.5 py-2.5 space-y-2"
                             data-testid="task-awaiting-approval-panel">
                            <p class="text-[13px] font-semibold text-[#b45309]">Awaiting approval</p>
                            <p class="text-[12px] text-[#92400e] leading-snug">
                                Submitted to the Approval Queue. Approve here, or review it on the queue page.
                            </p>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.btn variant="primary" size="sm" type="button"
                                         @click="moveTask(activeTask.id, 'done')"
                                         data-testid="task-approve-complete-btn">
                                    Approve &amp; mark done
                                </x-ui.btn>
                                <a href="{{ route('workflow-queues') }}"
                                   class="text-[12.5px] font-semibold text-[#2563eb] hover:underline"
                                   data-testid="task-open-approval-queue">
                                    Open Approval Queue →
                                </a>
                            </div>
                        </div>

                        {{-- Human / staff task --}}
                        <div x-show="drawerPhase === 'human_open'" x-cloak data-testid="task-mark-complete">
                            <x-ui.btn variant="primary" size="sm" type="button"
                                     @click="moveTask(activeTask.id, 'done')"
                                     data-testid="task-mark-complete-btn">
                                Mark complete
                            </x-ui.btn>
                        </div>
                    </div>

                    <div x-show="drawerPhase === 'completed'"
                         class="rounded-xl border border-[#d1fadf] bg-[#ecfdf3] px-3.5 py-2.5"
                         data-testid="task-completed-banner">
                        <p class="text-[13px] font-semibold text-[#067647]">Completed</p>
                        <p x-show="activeTask?.completed_at" class="text-[12px] text-[#027a48] mt-0.5"
                           x-text="'Finished ' + activeTask.completed_at"></p>
                    </div>

                    <div>
                        <p class="task-drawer-label">Description</p>
                        <p class="text-[13px] text-[#334155] leading-relaxed whitespace-pre-wrap"
                           x-text="activeTask?.description || 'No description provided.'"></p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="task-drawer-label">Assignee</p>
                            <p class="text-[13px] font-semibold text-[#0f172a]" x-text="activeTask?.assignee || 'Unassigned'"></p>
                        </div>
                        <div>
                            <p class="task-drawer-label">Due date</p>
                            <p class="text-[13px] font-semibold text-[#0f172a]" x-text="activeTask?.due_date"></p>
                        </div>
                        <div>
                            <p class="task-drawer-label">Priority</p>
                            <p class="text-[13px] font-semibold text-[#0f172a]" x-text="activeTask?.priority_label"></p>
                        </div>
                        <div x-show="activeTask?.about">
                            <p class="task-drawer-label">Related to</p>
                            <p class="text-[13px] font-semibold text-[#0f172a]" x-text="activeTask?.about"></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 pt-1 border-t border-[#eef2f9]">
                        <div x-show="activeTask?.created_at">
                            <p class="task-drawer-label">Created</p>
                            <p class="text-[12px] text-[#64748b]" x-text="activeTask?.created_at + (activeTask?.created_by ? ' · ' + activeTask.created_by : '')"></p>
                        </div>
                        <div x-show="activeTask?.completed_at">
                            <p class="task-drawer-label">Completed</p>
                            <p class="text-[12px] text-[#64748b]" x-text="activeTask?.completed_at"></p>
                        </div>
                    </div>

                    <a x-show="activeTask?.related_url"
                       :href="activeTask?.related_url"
                       class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-[#2563eb] hover:underline">
                        Open related record →
                    </a>

                    <div class="pt-4 border-t border-[#eef2f9] space-y-3" data-testid="task-comments">
                        <p class="task-drawer-label">Comments</p>
                        <div class="space-y-2.5 max-h-56 overflow-y-auto">
                            <template x-for="comment in taskComments" :key="comment.id">
                                <div class="rounded-xl border border-[#eef2f9] bg-[#f8fbff] px-3 py-2.5">
                                    <div class="flex items-center justify-between gap-2 mb-1">
                                        <span class="text-[12px] font-semibold text-[#0f172a]" x-text="comment.user_name"></span>
                                        <span class="text-[11px] text-[#94a3b8]" x-text="comment.created_at"></span>
                                    </div>
                                    <p class="text-[12.5px] text-[#334155] whitespace-pre-wrap" x-text="comment.body"></p>
                                </div>
                            </template>
                            <p x-show="!taskCommentsLoading && !taskComments.length"
                               class="text-[12px] text-[#94a3b8]">No comments yet.</p>
                            <p x-show="taskCommentsLoading" class="text-[12px] text-[#94a3b8]">Loading comments…</p>
                        </div>
                        <div x-show="canManageTasks" class="flex gap-2 items-start">
                            <textarea x-model="newCommentBody"
                                      rows="2"
                                      placeholder="Add a comment…"
                                      class="task-drawer-input resize-none min-h-[64px] flex-1"
                                      data-testid="task-comment-input"></textarea>
                            <x-ui.btn variant="primary" size="sm" type="button"
                                     @click="addTaskComment()"
                                     x-bind:disabled="taskCommentSaving || !String(newCommentBody || '').trim()"
                                     data-testid="task-comment-submit">
                                <span x-text="taskCommentSaving ? '…' : 'Post'"></span>
                            </x-ui.btn>
                        </div>
                    </div>
                </div>

                {{-- Edit mode --}}
                <div x-show="activeTask && !taskDrawerLoading && taskDrawerEditing" x-cloak class="space-y-4">
                    <div>
                        <label class="task-drawer-label" for="drawer-task-title">Title</label>
                        <input id="drawer-task-title" type="text" x-model="taskDrawerForm.title" class="task-drawer-input">
                    </div>
                    <div>
                        <label class="task-drawer-label" for="drawer-task-description">Description</label>
                        <textarea id="drawer-task-description" rows="4" x-model="taskDrawerForm.description" class="task-drawer-input resize-none min-h-[96px]"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="task-drawer-label" for="drawer-task-status">Status</label>
                            <select id="drawer-task-status" x-model="taskDrawerForm.status" class="task-drawer-input">
                                <template x-for="status in boardStatuses" :key="status.key">
                                    <option :value="status.key" x-text="status.label"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="task-drawer-label" for="drawer-task-priority">Priority</label>
                            <select id="drawer-task-priority" x-model="taskDrawerForm.priority" class="task-drawer-input">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="task-drawer-label" for="drawer-task-due-date">Due date</label>
                        <div class="relative task-drawer-date-wrap">
                            <input id="drawer-task-due-date"
                                   type="text"
                                   x-model="taskDrawerForm.due_date"
                                   placeholder="Select date"
                                   autocomplete="off"
                                   class="task-drawer-input pr-10">
                        </div>
                    </div>
                    <div>
                        <label class="task-drawer-label" for="drawer-task-assignee">Assign to</label>
                        <select id="drawer-task-assignee"
                                @change="setDrawerAssignee($event)"
                                class="task-drawer-input">
                            <option value="">Unassigned</option>
                            @foreach($assignees as $a)
                                <option value="{{ $a['type'] }}:{{ $a['id'] }}">{{ $a['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div x-show="taskDrawerEditing" class="px-6 py-4 border-t border-[#eef2f9] bg-[#fafbfd] flex items-center justify-end gap-2 shrink-0">
                <x-ui.btn variant="outline" size="sm" type="button" @click="cancelTaskDrawerEdit()">Cancel</x-ui.btn>
                <x-ui.btn variant="primary" size="sm" type="button" @click="saveTaskDrawer()" x-bind:disabled="taskDrawerSaving">
                    <span x-text="taskDrawerSaving ? 'Saving…' : 'Save changes'"></span>
                </x-ui.btn>
            </div>
        </div>
    </div>
</template>

<style>
    .task-drawer-label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #94a3b8;
        margin-bottom: 6px;
    }

    .task-drawer-input {
        display: block;
        width: 100%;
        height: 40px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #fff;
        padding: 0 12px;
        font-size: 13px;
        color: #0f172a;
        outline: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    textarea.task-drawer-input {
        height: auto;
        padding-top: 10px;
        padding-bottom: 10px;
    }

    .task-drawer-input:focus {
        border-color: #93c5fd;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .task-drawer-date-wrap .flatpickr-calendar {
        z-index: 999999;
    }
</style>
