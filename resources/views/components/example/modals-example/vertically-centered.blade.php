<x-common.component-card title="Vertically Centered Modal">
    <x-ui.button size="sm" variant="primary" @click="$dispatch('open-vertically-centered-modal')">Open Modal</x-ui.button>

    <x-ui.modal x-data="{ open: false }" @open-vertically-centered-modal.window="open = true" :isOpen="false"
        class="max-w-[570px]">
        <div class="relative w-full rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-10">
            <div class="text-center">
                <h4 class="mb-2 text-2xl font-semibold text-gray-800 dark:text-white/90 sm:text-title-sm">
                    All Done! Success Confirmed
                </h4>
                <p class="text-sm leading-6 text-gray-500 dark:text-gray-400">
                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque euismod est quis
                    mauris lacinia pharetra.
                </p>

                <div class="flex items-center justify-center w-full gap-3 mt-8">
                    <x-ui.button size="sm" variant="outline" @click="open = false"> Close </x-ui.button>
                    <x-ui.button size="sm" @click="open = false"> Save Changes </x-ui.button>
                </div>
            </div>
        </div>
    </x-ui.modal>
</x-common.component-card>
