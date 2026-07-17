<div x-show="newTaskOpen"
     x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
    <div class="absolute inset-0 bg-[#0f172a]/45 backdrop-blur-[1px]" @click="newTaskOpen = false"></div>

    <div class="relative w-full max-w-xl rounded-2xl border border-[#e2e8f0] bg-white shadow-[0_20px_50px_rgba(15,23,42,0.12)] overflow-hidden"
         @click.stop>
        <div class="flex items-start justify-between gap-4 px-6 py-5 border-b border-[#eef2f9] bg-[#f8fbff]">
            <div>
                <h2 class="text-[17px] font-bold text-[#0f172a] tracking-tight">New Task</h2>
                <p class="text-[12px] text-[#64748b] mt-1">Add a task to your board and assign it to staff or an AI agent.</p>
            </div>
            <button type="button"
                    class="shrink-0 rounded-lg p-1.5 text-[#94a3b8] hover:text-[#0f172a] hover:bg-white transition-colors"
                    @click="newTaskOpen = false"
                    aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="px-6 py-5 space-y-4">
            <div>
                <label for="task-title" class="task-modal-label">Title <span class="text-[#dc2626]">*</span></label>
                <input id="task-title"
                       type="text"
                       x-model="form.title"
                       placeholder="What needs to be done?"
                       class="task-modal-input">
            </div>

            <div>
                <label for="task-description" class="task-modal-label">Description</label>
                <textarea id="task-description"
                          x-model="form.description"
                          rows="3"
                          placeholder="Add optional details, context, or next steps…"
                          class="task-modal-input resize-none min-h-[88px]"></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="task-priority" class="task-modal-label">Priority</label>
                    <select id="task-priority" x-model="form.priority" class="task-modal-input">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="task-due-date-wrap">
                    <label for="task-due-date" class="task-modal-label">Due date</label>
                    <div class="relative">
                        <input id="task-due-date"
                               type="text"
                               x-model="form.due_date"
                               placeholder="Select date"
                               autocomplete="off"
                               class="task-modal-input pr-10">
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-[#94a3b8]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M8 2C8.41421 2 8.75 2.33579 8.75 2.75V3.75H15.25V2.75C15.25 2.33579 15.5858 2 16 2C16.4142 2 16.75 2.33579 16.75 2.75V3.75H18.5C19.7426 3.75 20.75 4.75736 20.75 6V9V19C20.75 20.2426 19.7426 21.25 18.5 21.25H5.5C4.25736 21.25 3.25 20.2426 3.25 19V9V6C3.25 4.75736 4.25736 3.75 5.5 3.75H7.25V2.75C7.25 2.33579 7.58579 2 8 2ZM8 5.25H5.5C5.08579 5.25 4.75 5.58579 4.75 6V8.25H19.25V6C19.25 5.58579 18.9142 5.25 18.5 5.25H16H8ZM19.25 9.75H4.75V19C4.75 19.4142 5.08579 19.75 5.5 19.75H18.5C18.9142 19.75 19.25 19.4142 19.25 19V9.75Z" fill="currentColor"/>
                            </svg>
                        </span>
                    </div>
                </div>
            </div>

            <div>
                <label for="task-assignee" class="task-modal-label">Assign to</label>
                <select id="task-assignee"
                        @change="const o = $event.target.selectedOptions[0]; form.assignee_type = o.dataset.type || 'user'; form.assignee_user_id = o.dataset.type === 'user' ? o.value : ''; form.assignee_agent_id = o.dataset.type === 'agent' ? o.value : '';"
                        class="task-modal-input">
                    <option value="">Select assignee…</option>
                    @foreach($assignees as $a)
                        <option value="{{ $a['id'] }}" data-type="{{ $a['type'] }}">{{ $a['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="task-client" class="task-modal-label">Client <span class="font-normal text-[#94a3b8]">(optional)</span></label>
                    <select id="task-client" x-model="form.client_id" class="task-modal-input">
                        <option value="">None</option>
                        @foreach(($clients ?? []) as $c)
                            <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="task-caregiver" class="task-modal-label">Caregiver <span class="font-normal text-[#94a3b8]">(optional)</span></label>
                    <select id="task-caregiver" x-model="form.employee_id" class="task-modal-input">
                        <option value="">None</option>
                        @foreach(($caregivers ?? []) as $cg)
                            <option value="{{ $cg['id'] }}">{{ $cg['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-[#eef2f9] bg-[#fafbfd]">
            <x-ui.btn variant="outline" size="sm" type="button" @click="newTaskOpen = false">Cancel</x-ui.btn>
            <x-ui.btn variant="primary" size="sm" type="button" @click="createTask()">Create task</x-ui.btn>
        </div>
    </div>
</div>

<style>
    .task-modal-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 6px;
    }

    .task-modal-input {
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

    textarea.task-modal-input {
        height: auto;
        padding-top: 10px;
        padding-bottom: 10px;
    }

    .task-modal-input::placeholder {
        color: #94a3b8;
    }

    .task-modal-input:focus {
        border-color: #93c5fd;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .task-due-date-wrap .flatpickr-calendar {
        z-index: 60;
    }
</style>
