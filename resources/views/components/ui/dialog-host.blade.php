<div
    x-data
    x-cloak
    x-show="$store.dialog.open"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-[99999] flex items-center justify-center p-4 sm:p-6"
    role="dialog"
    aria-modal="true"
    :aria-labelledby="$store.dialog.open ? 'app-dialog-title' : null"
    @keydown.escape.window="$store.dialog.dismiss()"
>
    <div class="absolute inset-0 bg-[#0f172a]/40 backdrop-blur-[2px]" @click="$store.dialog.dismiss()"></div>

    <div
        x-show="$store.dialog.open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95 translate-y-1"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-1"
        @click.stop
        class="relative w-full max-w-md rounded-2xl border border-[#e2e8f0] bg-white shadow-xl overflow-hidden"
    >
        <div class="px-6 pt-6 pb-4">
            <div class="flex items-start gap-4">
                <div
                    class="shrink-0 w-11 h-11 rounded-xl flex items-center justify-center"
                    :class="{
                        'bg-[#fee2e2] text-[#dc2626]': $store.dialog.variant === 'danger',
                        'bg-[#fef3c7] text-[#b45309]': $store.dialog.variant === 'warning',
                        'bg-[#dbeafe] text-[#2563eb]': $store.dialog.variant === 'primary' || $store.dialog.variant === 'info',
                        'bg-[#d1fae5] text-[#059669]': $store.dialog.variant === 'success',
                    }"
                >
                    <svg x-show="$store.dialog.variant === 'danger'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    <svg x-show="$store.dialog.variant === 'warning'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <svg x-show="$store.dialog.variant === 'success'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <svg x-show="!['danger', 'warning', 'success'].includes($store.dialog.variant)" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>

                <div class="min-w-0 flex-1 pt-0.5">
                    <h3 id="app-dialog-title" class="text-[16px] font-bold text-[#0f172a] leading-snug" x-text="$store.dialog.title"></h3>
                    <p class="mt-2 text-[13px] text-[#64748b] leading-relaxed" x-text="$store.dialog.message"></p>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 bg-[#f8fafc] border-t border-[#f1f5f9] flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
            <button
                type="button"
                x-show="$store.dialog.mode === 'confirm'"
                @click="$store.dialog.dismiss()"
                class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-[13px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] hover:bg-[#f8fafc] transition-colors"
                x-text="$store.dialog.cancelLabel"
            ></button>
            <button
                type="button"
                @click="$store.dialog.accept()"
                class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-[13px] font-semibold text-white transition-colors"
                :class="{
                    'bg-[#dc2626] hover:bg-[#b91c1c]': $store.dialog.variant === 'danger',
                    'bg-[#d97706] hover:bg-[#b45309]': $store.dialog.variant === 'warning',
                    'bg-[#059669] hover:bg-[#047857]': $store.dialog.variant === 'success',
                    'bg-[#2563eb] hover:bg-[#1d4ed8]': !['danger', 'warning', 'success'].includes($store.dialog.variant),
                }"
                x-text="$store.dialog.confirmLabel"
            ></button>
        </div>
    </div>
</div>
