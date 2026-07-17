@php
    $supportedLanguages = $presenter['supportedLanguages'] ?? config('global_settings.supported_languages', []);
    $selectedLanguages = old('notifications.supported_languages', $settings['notifications.supported_languages'] ?? ['en', 'ar']);
    if (! is_array($selectedLanguages)) {
        $selectedLanguages = ['en', 'ar'];
    }
    $badgeClasses = [
        'blue' => 'bg-blue-50 text-blue-700 border border-blue-100',
        'purple' => 'bg-purple-50 text-purple-700 border border-purple-100',
    ];
@endphp

<x-global-settings.section-card title="Language" subtitle="Agent & app languages" error-prefixes="notifications">
    <x-global-settings.field-row label="Supported languages" align="start">
        <div class="flex flex-wrap gap-3">
            @foreach($supportedLanguages as $code => $meta)
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="notifications[supported_languages][]" value="{{ $code }}" @checked(in_array($code, $selectedLanguages, true)) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wide {{ $badgeClasses[$meta['badge'] ?? 'blue'] ?? $badgeClasses['blue'] }}">
                        {{ $meta['label'] }}
                    </span>
                </label>
            @endforeach
        </div>
    </x-global-settings.field-row>

    <div class="mt-6 pt-5 border-t border-slate-50">
        <h4 class="text-sm font-black text-[#1e293b] mb-2">Request templates</h4>
        <p class="text-sm font-bold text-[#64748b] opacity-70 mb-3">Agency-configurable Send Request templates for communications.</p>
        <a href="{{ route('request-templates.index') }}" class="inline-flex items-center gap-2 bg-[#f0f7ff] text-blue-600 px-5 py-2.5 rounded-xl text-xs font-black tracking-wide hover:bg-blue-600 hover:text-white hover:shadow-lg hover:shadow-blue-200 transition-all border border-blue-100/50">
            Manage Request Templates
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"></path></svg>
        </a>
    </div>
</x-global-settings.section-card>
