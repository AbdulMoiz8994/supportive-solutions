@php
    $user = auth()->user();
    if ($user->isSuperAdmin()) {
        $locations = \App\Models\Location::where('is_active', true)->get();
    } else {
        $locations = $user->locations;
    }
@endphp

<div x-data="{ 
    open: false, 
    selectedName: '{{ session('selected_location_name', 'Company Wide') }}',
    switchLocation(id, name) {
        $refs.locationIdInput.value = id;
        $refs.locationForm.submit();
    }
}" class="relative">
    <button 
        @click="open = !open" 
        class="flex items-center gap-2 px-3 py-1.5 text-xs font-black text-gray-600 bg-gray-50 border border-gray-100 rounded-xl hover:bg-white hover:border-brand-500 transition-all focus:outline-none focus:ring-4 focus:ring-brand-500/5 shadow-sm"
    >
        <span class="text-brand-500">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
        </span>
        <span class="hidden sm:inline" x-text="'Office: ' + selectedName"></span>
        <span class="inline sm:hidden" x-text="selectedName"></span>
        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
    </button>

    <form x-ref="locationForm" action="{{ route('location.switch') }}" method="POST" class="hidden">
        @csrf
        <input type="hidden" name="location_id" x-ref="locationIdInput">
    </form>

    <div 
        x-show="open" 
        @click.away="open = false"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-2xl border border-gray-100 p-2 z-99999"
        x-cloak
    >
        <div class="px-3 py-2 text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50 mb-1">Select Office Context</div>
        
        @if($user->isSuperAdmin())
        <button 
            @click="switchLocation('all', 'Company Wide')" 
            class="w-full text-left px-3 py-2.5 text-xs font-bold rounded-lg transition-all"
            :class="selectedName === 'Company Wide' ? 'text-brand-600 bg-brand-50 shadow-sm' : 'text-gray-500 hover:bg-gray-50'"
        >
            <div class="flex items-center justify-between">
                <span>Company Wide</span>
                <template x-if="selectedName === 'Company Wide'">
                    <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                </template>
            </div>
        </button>
        @endif

        @foreach($locations as $loc)
        <button 
            @click="switchLocation('{{ $loc->id }}', '{{ $loc->name }}')" 
            class="w-full text-left px-3 py-2.5 text-xs font-bold rounded-lg transition-all"
            :class="selectedName === '{{ $loc->name }}' ? 'text-brand-600 bg-brand-50 shadow-sm' : 'text-gray-500 hover:bg-gray-50'"
        >
            <div class="flex items-center justify-between">
                <span>{{ $loc->name }}</span>
                <template x-if="selectedName === '{{ $loc->name }}'">
                    <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                </template>
            </div>
        </button>
        @endforeach
    </div>
</div>
