<div x-show="boardStatusesOpen" x-cloak data-testid="board-statuses-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-[#0f172a]/40" @click="boardStatusesOpen = false"></div>
    <div class="relative w-full max-w-lg rounded-2xl border border-[#e2e8f0] bg-white shadow-xl p-5 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-[16px] font-bold text-[#0f172a]">Manage statuses</h2>
                <p class="text-[12px] text-[#64748b] mt-1">Drag to reorder, or add, rename, and remove task statuses.</p>
            </div>
            <button type="button" class="text-[#94a3b8] hover:text-[#0f172a]" @click="boardStatusesOpen = false">✕</button>
        </div>

        <div class="space-y-2">
            <template x-for="(status, statusIndex) in manageBoardStatuses" :key="status.id">
                <div class="flex items-center gap-2 rounded-xl border border-[#e2e8f0] px-3 py-2.5 transition-colors"
                     :class="statusDragOverIndex === statusIndex ? 'border-[#2563eb] bg-[#eff6ff]' : ''"
                     @dragover.prevent="onStatusDragOver(statusIndex)"
                     @drop.prevent="onStatusDrop(statusIndex)">
                    <button type="button"
                            draggable="true"
                            @dragstart.stop="onStatusDragStart($event, statusIndex)"
                            @dragend.stop="onStatusDragEnd()"
                            class="shrink-0 text-[#94a3b8] hover:text-[#475569] cursor-grab active:cursor-grabbing px-0.5"
                            title="Drag to reorder">⋮⋮</button>
                    <div class="flex-1 min-w-0">
                        <input type="text"
                               x-model="status.label"
                               class="w-full rounded-lg border border-[#e2e8f0] px-2.5 py-1.5 text-[13px] font-semibold text-[#0f172a]">
                    </div>
                    <label class="flex items-center gap-1.5 text-[11px] text-[#64748b] shrink-0">
                        <input type="checkbox" x-model="status.is_closed" class="rounded border-[#cbd5e1]">
                        Closed
                    </label>
                    <x-ui.btn variant="outline" size="sm" type="button" @click="saveBoardStatus(status)">Save</x-ui.btn>
                    <button type="button"
                            class="text-[11px] font-semibold text-[#b91c1c] hover:underline shrink-0"
                            @click="deleteBoardStatus(status)">Remove</button>
                </div>
            </template>
        </div>

        <div class="rounded-xl border border-dashed border-[#e2e8f0] bg-[#f8fbff] p-3 space-y-2">
            <div class="text-[12px] font-semibold text-[#0f172a]">Add status</div>
            <div class="grid grid-cols-1 gap-2">
                <input type="text"
                       x-model="statusForm.label"
                       data-testid="new-status-label"
                       placeholder="Status name (e.g. On hold)"
                       class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
                <label class="flex items-center gap-2 text-[12px] text-[#64748b] px-1">
                    <input type="checkbox" x-model="statusForm.is_closed" class="rounded border-[#cbd5e1]">
                    Closed status
                </label>
            </div>
            <div class="flex justify-end">
                <x-ui.btn variant="primary" size="sm" type="button" data-testid="add-board-status-btn" @click="addBoardStatus()">+ Add status</x-ui.btn>
            </div>
        </div>
    </div>
</div>
