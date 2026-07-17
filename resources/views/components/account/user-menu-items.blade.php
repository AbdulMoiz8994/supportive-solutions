@php
    $canSettings = \App\Helpers\SettingsHelper::canAccessHome();
@endphp

<div class="p-1">
    <a href="{{ route('profile') }}" class="flex items-center gap-2 px-3 py-2 text-[12px] font-bold text-[#64748b] hover:bg-blue-50 hover:text-blue-600 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
        My Profile
    </a>
    @if($canSettings)
        <a href="{{ route('settings.index') }}" class="flex items-center gap-2 px-3 py-2 text-[12px] font-bold text-[#64748b] hover:bg-blue-50 hover:text-blue-600 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><circle cx="12" cy="12" r="3" stroke-width="2"></circle></svg>
            Settings
        </a>
    @endif
</div>
<form method="POST" action="{{ route('logout') }}" class="p-1 border-t border-gray-50">
    @csrf
    <button type="submit" class="flex items-center w-full gap-2 px-3 py-2 text-[12px] font-bold text-red-500 hover:bg-red-50 hover:text-red-600 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
        Sign out
    </button>
</form>
