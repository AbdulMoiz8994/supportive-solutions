@php
    $c = $caregiver;
    $folders = [
        ['Identity', '2 items · ID, SSN card', 'blue'],
        ['Employment', '1 item · updated '.($c->updated_at?->format('M j') ?? '—'), 'indigo'],
        ['CHAMPS & Enrollment', '2 items · MSA-204, approval letter', 'sky'],
        ['Live-In Exemption', '1 item · BPHASA-2421', 'violet'],
        ['Background Checks', $c->backgroundChecks->count().' items · ICHAT, SAM, OIG, TB', 'green'],
        ['Pay Stubs', $c->payRecords->where('status','Paid')->count().' items · Feb–Apr', 'amber'],
        ['Compliance Forms', $c->complianceForms->where('status','Submitted')->count().' items · Feb–Apr', 'rose'],
        ['Forms', $c->documents->where('type', 'form')->count().' items · signed paperwork', 'slate'],
        ['Correspondence', '1 item', 'cyan'],
    ];
@endphp

@include('partials.document-checklist')

<div class="bg-blue-50/60 border border-blue-100 rounded-2xl px-5 py-4 mb-5 flex items-center justify-between">
    <div>
        <p class="text-[13px] font-bold text-blue-700">New files are auto-classified and filed into the right folder</p>
        <p class="text-[12px] text-blue-600/80 mt-0.5">Scan or upload anything — the parser detects the type (I-9, W-4, check result, pay stub…) and files it. Create your own folders and move files around.</p>
    </div>
    <button class="px-5 py-2.5 bg-[#2563eb] text-white rounded-xl text-[12px] font-bold shadow-lg shadow-blue-100 shrink-0">Scan / Upload</button>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    @foreach($folders as [$name, $sub, $tone])
    <div class="bg-white rounded-[18px] border border-[#e2e8f0] p-5 hover:border-blue-300 transition-all cursor-pointer">
        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-500 flex items-center justify-center mb-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
        </div>
        <p class="text-[14px] font-bold text-[#1e293b]">{{ $name }}</p>
        <p class="text-[11px] text-[#94a3b8] mt-1">{{ $sub }}</p>
    </div>
    @endforeach
    <div class="bg-blue-50/40 rounded-[18px] border-2 border-dashed border-blue-200 p-5 flex flex-col items-center justify-center text-center cursor-pointer">
        <svg class="w-6 h-6 text-blue-500 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <p class="text-[13px] font-bold text-blue-600">New folder</p>
    </div>
</div>

