@if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-transition class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-[#d1fadf] bg-[#ecfdf3] px-4 py-3 text-sm font-semibold text-[#067647]">
        <span>{{ session('success') }}</span>
        <button type="button" @click="show = false" class="text-[#067647]/60 hover:text-[#067647] leading-none">&times;</button>
    </div>
@endif

@if(session('warning'))
    <div x-data="{ show: true }" x-show="show" x-transition class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-[#fde68a] bg-[#fffbeb] px-4 py-3 text-sm font-semibold text-[#92400e]">
        <span>{{ session('warning') }}</span>
        <button type="button" @click="show = false" class="text-[#92400e]/60 hover:text-[#92400e] leading-none">&times;</button>
    </div>
@endif

@if(($showValidationErrors ?? true) && $errors->any())
    <div class="mb-4 rounded-xl border border-[#fecaca] bg-[#fef2f2] px-4 py-3 text-sm text-[#b91c1c]" role="alert">
        <ul class="list-disc list-inside space-y-0.5 font-medium">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
