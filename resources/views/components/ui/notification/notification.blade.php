@props([
    'type' => 'info', // success | info | warning | error
    'message' => '',
])

<div
    x-data="{
        isVisible: true,
        close() { this.isVisible = false },
        type: '{{ $type }}',
        borderColor() {
            return this.type === 'success' ? 'border-success-500'
                : this.type === 'info' ? 'border-blue-light-500'
                : this.type === 'warning' ? 'border-warning-500'
                : 'border-error-500'
        },
        shadowClass() {
            return (this.type === 'info') ? 'shadow-theme-lg' : 'shadow-theme-sm'
        },
        iconBg() {
            return this.type === 'success'
                ? 'bg-success-50 dark:bg-success-500/[0.15]'
                : this.type === 'info'
                    ? 'bg-blue-light-50 dark:bg-blue-light-500/[0.15]'
                    : this.type === 'warning'
                        ? 'bg-warning-50 dark:bg-warning-500/[0.15]'
                        : 'bg-error-50 dark:bg-error-500/[0.15]'
        },
        iconColor() {
            return this.type === 'success'
                ? 'text-success-600 dark:text-success-500'
                : this.type === 'info'
                    ? 'text-blue-light-500 dark:text-blue-light-500'
                    : this.type === 'warning'
                        ? 'text-warning-600 dark:text-orange-400'
                        : 'text-error-600 dark:text-error-500'
        }
    }"
    x-show="isVisible"
    x-cloak
    class="flex items-center justify-between gap-3 w-full sm:max-w-[340px] rounded-md border-b-4 bg-white p-3 shadow-theme-sm dark:bg-[#1E2634]"
    :class="[borderColor(), shadowClass()]"
    role="status"
    aria-live="polite"
>
    <div class="flex items-center gap-4">
        <div
            class="flex items-center justify-center w-10 h-10 rounded-lg shrink-0"
            :class="iconBg()"
        >
            {{-- Icons per type --}}
            <template x-if="type === 'success'">
                <svg class="w-5 h-5" x-bind:class="iconColor()" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M20 6L9 17L4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </template>

            <template x-if="type === 'info'">
                <svg class="w-5 h-5" x-bind:class="iconColor()" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M12 8V12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M12 16H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
                </svg>
            </template>

            <template x-if="type === 'warning'">
                <svg class="w-5 h-5" x-bind:class="iconColor()" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M10.29 3.86L1.82 18a1 1 0 0 0 .85 1.5h18.66a1 1 0 0 0 .85-1.5L13.71 3.86a1 1 0 0 0-1.72 0z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M12 9v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M12 17h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </template>

            <template x-if="type === 'error'">
                <svg class="w-5 h-5" x-bind:class="iconColor()" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </template>
        </div>

        <h4 class="text-sm text-gray-800 sm:text-base dark:text-white/90">
            {{ $message }}
        </h4>
    </div>

    <button @click="close()" class="text-gray-400 hover:text-gray-800 dark:hover:text-white/90" type="button" aria-label="Close notification">
        <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5418C5.65237 16.9323 5.65237 17.5655 6.04289 17.956C6.43342 18.3465 7.06658 18.3465 7.45711 17.956L11.9987 13.4144L16.5408 17.9565C16.9313 18.347 17.5645 18.347 17.955 17.9565C18.3455 17.566 18.3455 16.9328 17.955 16.5423L13.4129 12.0002L17.955 7.45808C18.3455 7.06756 18.3455 6.43439 17.955 6.04387C17.5645 5.65335 16.9313 5.65335 16.5408 6.04387L11.9987 10.586L7.45711 6.04439C7.06658 5.65386 6.43342 5.65386 6.04289 6.04439C5.65237 6.43491 5.65237 7.06808 6.04289 7.4586L10.5845 12.0002L6.04289 16.5418Z" fill="currentColor"/>
        </svg>
    </button>
</div>
