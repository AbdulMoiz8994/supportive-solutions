@props([
    'items' => [
        ['label' => 'Lorem ipsum dolor sit amet', 'checked' => false],
        ['label' => 'It is a long established fact reader', 'checked' => false],
        ['label' => 'Lorem ipsum dolor sit amet', 'checked' => false],
        ['label' => 'Lorem ipsum dolor sit amet', 'checked' => false],
        ['label' => 'Lorem ipsum dolor sit amet', 'checked' => false],
    ],
])

<div
    x-data="{
        checkboxes: {{ json_encode($items) }},
        toggle(index) {
            this.checkboxes[index].checked = !this.checkboxes[index].checked
        }
    }"
    class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] sm:w-fit"
>
    <ul class="flex flex-col">
        <template x-for="(item, index) in checkboxes" :key="index">
            <li class="border-b border-gray-200 px-3 py-2.5 last:border-b-0 dark:border-gray-800">
                <div>
                    <label
                        :for="`listCheckbox${index}`"
                        class="flex items-center text-sm text-gray-500 cursor-pointer select-none dark:text-gray-400"
                    >
                        <span class="relative">
                            <input
                                type="checkbox"
                                class="sr-only"
                                :id="`listCheckbox${index}`"
                                x-model="item.checked"
                            />
                            <span
                                class="flex items-center justify-center w-4 h-4 mr-2 border rounded-sm"
                                :class="item.checked
                                    ? 'border-brand-500 bg-brand-500'
                                    : 'bg-transparent border-gray-300 dark:border-gray-700'"
                                @click="toggle(index)"
                            >
                                <span :class="item.checked ? 'opacity-100' : 'opacity-0'">
                                    <svg
                                        width="12"
                                        height="12"
                                        viewBox="0 0 12 12"
                                        fill="none"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <path
                                            d="M10 3L4.5 8.5L2 6"
                                            stroke="white"
                                            stroke-width="1.6666"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </span>
                            </span>
                        </span>
                        <span x-text="item.label"></span>
                    </label>
                </div>
            </li>
        </template>
    </ul>
</div>
