@if (session('success'))
    <div class="mb-4" role="status" aria-live="polite">
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    </div>
@endif

@if (session('error'))
    <div class="mb-4" role="alert" aria-live="assertive">
        <x-ui.alert variant="error">{{ session('error') }}</x-ui.alert>
    </div>
@endif

@if ($errors->any())
    <div class="mb-4" role="alert" aria-live="assertive">
        <x-ui.alert variant="error" title="Please correct the following:">
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    </div>
@endif
