{{-- Documents --}}
@php
    $docCount = $client->documents->count();
    $docExportService = app(\App\Services\ClientDocumentsExportService::class);
    $groupedDocs = collect($docExportService->groupedDocuments($client));

    $folderMeta = [
        'intake' => ['name' => 'Intake & Identity', 'sub' => 'created at intake',
         'icon' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h3M15 12h3M7 16h10"/>'],
        'eligibility' => ['name' => 'Eligibility', 'sub' => 'verification records',
         'icon' => '<path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/>'],
        'authorizations' => ['name' => 'Authorizations', 'sub' => 'PA letters',
         'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        'compliance' => ['name' => 'Compliance Forms', 'sub' => 'one per month',
         'icon' => '<path d="M9 5h6M9 9h6M9 13h4"/><path d="M5 3h14a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/>'],
        'forms' => ['name' => 'Forms', 'sub' => 'signed paperwork',
         'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/>'],
        'billing' => ['name' => 'Billing — Claims & EOBs', 'sub' => 'claims and remittances',
         'icon' => '<rect x="3" y="5" width="18" height="14" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/>'],
        'general' => ['name' => 'General', 'sub' => 'other documents',
         'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
    ];

    $folders = collect($folderMeta)->map(function ($meta, $key) use ($groupedDocs) {
        $items = $groupedDocs->where('folder', $key)->values();
        $latest = $items->first()['document'] ?? null;

        return array_merge($meta, [
            'key' => $key,
            'count' => $items->count(),
            'updated' => $latest?->created_at?->format('M j') ?? '—',
            'files' => $items->map(function ($row) {
                $document = $row['document'];

                return [
                    'document' => $document,
                    'name' => $document->name ?: $document->original_filename ?: basename($document->path),
                    'date' => $document->created_at?->format('M j, Y') ?? '—',
                    'source' => $document->category ?: 'Uploaded',
                ];
            })->all(),
        ]);
    })->filter(fn ($folder) => $folder['count'] > 0)->values()->all();

    $folderNames = array_column($folders, 'name');
@endphp

<div x-show="activeTab === 'documents'" x-cloak class="space-y-4"
     x-data="{ view: 'folders', openFolder: null, scanOpen: false, newFolderOpen: false, newFolderName: '' }">

    @include('partials.document-checklist')

    {{-- Auto-classify banner --}}
    <div class="rounded-2xl border border-[#cdddf5] bg-[#e8f0fc] p-5 flex items-center justify-between gap-4">
        <div class="flex items-start gap-3.5">
            <span class="w-11 h-11 rounded-xl bg-[#2563eb] text-white flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 4v5"/></svg>
            </span>
            <div>
                <h3 class="text-base font-bold text-[#1d4ed8]">New files are auto-classified and dropped into the right folder</h3>
                <p class="text-sm text-[#3b6cc4] mt-0.5">Scan or upload anything — the parser detects the type and files it. You can also create your own folders and move files around.</p>
            </div>
        </div>
        <button type="button" @click="scanOpen = true" class="inline-flex items-center gap-1.5 bg-[#2563eb] text-white text-sm font-semibold rounded-[9px] px-3.5 py-2 hover:bg-[#1d4ed8] transition-colors shrink-0">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Scan / Upload
        </button>
    </div>

    {{-- Toolbar --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-2.5">
            <div class="relative">
                <svg class="w-4 h-4 text-[#94a3b8] absolute left-3 top-1/2 -translate-y-1/2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                <input type="text" placeholder="Search this client's documents…" class="w-[260px] pl-9 pr-3 py-2 rounded-[9px] border border-card-border bg-white text-sm outline-none focus:border-[#2563eb]">
            </div>
            <div class="inline-flex items-center gap-1 bg-card border border-card-border rounded-[10px] p-1">
                <button type="button" @click="view = 'folders'; openFolder = null" :class="view === 'folders' ? 'bg-white text-[#2563eb] shadow-sm' : 'text-[#64748b]'" class="inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-[7px] transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>Folders
                </button>
                <button type="button" @click="view = 'all'; openFolder = null" :class="view === 'all' ? 'bg-white text-[#2563eb] shadow-sm' : 'text-[#64748b]'" class="inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-[7px] transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>All files
                </button>
            </div>
        </div>
        <button type="button" @click="newFolderOpen = true" class="inline-flex items-center gap-1.5 bg-white text-[#475569] text-sm font-semibold rounded-[9px] px-3.5 py-2 border border-[#d8e2f0] hover:border-[#94a3b8] transition-colors">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New folder
        </button>
    </div>

    {{-- ── Folder grid ─────────────────────────────────────────────────────── --}}
    <div x-show="view === 'folders'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($folders as $f)
            <button type="button" @click="view = 'detail'; openFolder = '{{ $f['key'] }}'"
                class="text-left rounded-2xl border border-card-border bg-card p-5 hover:border-[#2563eb] hover:shadow-[0_4px_14px_rgba(37,99,235,0.08)] transition-all">
                <span class="w-11 h-11 rounded-xl bg-[#dbe7fa] text-[#2563eb] flex items-center justify-center mb-9">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $f['icon'] !!}</svg>
                </span>
                <div class="text-base font-bold text-[#0f172a] leading-tight">{{ $f['name'] }}</div>
                <div class="text-sm text-[#94a3b8] mt-1">{{ $f['count'] }} item{{ $f['count'] == 1 ? '' : 's' }} · updated {{ $f['updated'] }}</div>
            </button>
        @endforeach
        {{-- New folder tile --}}
        <button type="button" @click="newFolderOpen = true"
            class="rounded-2xl border-2 border-dashed border-[#9cc0f5] bg-[#eef4fb] p-5 flex flex-col items-center justify-center gap-1.5 text-[#2563eb] hover:bg-[#e3edfb] transition-colors min-h-[150px]">
            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span class="text-sm font-bold">New folder</span>
        </button>
    </div>

    {{-- ── Folder detail (one per folder, toggled by openFolder) ───────────── --}}
    @foreach($folders as $f)
        <div x-show="view === 'detail' && openFolder === '{{ $f['key'] }}'" x-cloak class="space-y-4">
            {{-- Breadcrumb back --}}
            <div class="flex items-center gap-1.5 text-sm font-medium">
                <button type="button" @click="view = 'folders'; openFolder = null" class="text-[#2563eb] hover:text-[#1d4ed8]">Documents</button>
                <span class="text-[#cbd5e1]">›</span>
                <span class="text-[#64748b]">{{ $f['name'] }}</span>
            </div>

            <x-ui.panel bodyClass="p-0">
                <div class="flex items-center justify-between px-5 pt-5 pb-4 flex-wrap gap-3">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-[#dbe7fa] text-[#2563eb] flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $f['icon'] !!}</svg>
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-[#0f172a] leading-tight">{{ $f['name'] }}</h3>
                            <p class="text-sm text-[#94a3b8] mt-0.5">{{ $f['count'] }} documents · {{ $f['sub'] }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-ui.btn variant="outline" size="sm">Rename folder</x-ui.btn>
                        <x-ui.btn variant="outline" size="sm" :href="route('clients.documents.download-all', $client)">Download all</x-ui.btn>
                        <x-ui.btn variant="primary" size="sm" x-on:click="scanOpen = true">Add to folder</x-ui.btn>
                    </div>
                </div>
                <div class="w-full overflow-x-auto no-scrollbar">
                    <table class="w-full min-w-[640px] border-collapse">
                        <thead>
                            <tr class="border-y border-card-border bg-white/60">
                                @foreach(['Document','Date','Source','Actions'] as $col)
                                    <th class="px-5 py-2.5 text-left text-xs font-bold text-[#94a3b8] uppercase tracking-wider whitespace-nowrap">{{ $col }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-card-border">
                            @foreach($f['files'] as $file)
                                <tr class="hover:bg-white/50 transition-colors">
                                    <td class="px-5 py-3.5 text-sm font-semibold text-[#0f172a] whitespace-nowrap">{{ $file['name'] }}</td>
                                    <td class="px-5 py-3.5 text-sm text-[#64748b] whitespace-nowrap">{{ $file['date'] }}</td>
                                    <td class="px-5 py-3.5 text-sm text-[#64748b] whitespace-nowrap">{{ $file['source'] }}</td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <div class="flex items-center gap-3 text-sm font-semibold text-[#2563eb]">
                                            @if(!empty($file['document']))
                                                <a href="{{ route('documents.download', $file['document']->id) }}" target="_blank" class="hover:text-[#1d4ed8]">View</a>
                                                <a href="{{ route('documents.download', $file['document']->id) }}" class="hover:text-[#1d4ed8]">Download</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{-- Drop zone --}}
                <div class="px-5 pb-5 pt-4">
                    <button type="button" @click="scanOpen = true" class="w-full rounded-xl border-2 border-dashed border-[#9cc0f5] bg-[#eef4fb] py-5 text-sm text-[#3b6cc4] hover:bg-[#e3edfb] transition-colors">
                        <span class="font-semibold text-[#2563eb]">Drop files into this folder</span> — or use Scan / Upload and pick this folder.
                    </button>
                </div>
            </x-ui.panel>
        </div>
    @endforeach

    {{-- ── All files (flat list) ──────────────────────────────────────────── --}}
    <div x-show="view === 'all'" x-cloak>
        <x-ui.panel bodyClass="p-0">
            <div class="px-5 pt-5 pb-4">
                <h3 class="text-base font-bold text-[#0f172a]">All files</h3>
                <p class="text-sm text-[#94a3b8] mt-0.5">Every document for this client across all folders.</p>
            </div>
            <div class="w-full overflow-x-auto no-scrollbar">
                <table class="w-full min-w-[640px] border-collapse">
                    <thead>
                        <tr class="border-y border-card-border bg-white/60">
                            @foreach(['Document','Folder','Date','Source','Actions'] as $col)
                                <th class="px-5 py-2.5 text-left text-xs font-bold text-[#94a3b8] uppercase tracking-wider whitespace-nowrap">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-card-border">
                        @foreach($folders as $f)
                            @foreach($f['files'] as $file)
                                <tr class="hover:bg-white/50 transition-colors">
                                    <td class="px-5 py-3 text-sm font-semibold text-[#0f172a] whitespace-nowrap">{{ $file['name'] }}</td>
                                    <td class="px-5 py-3 text-sm text-[#64748b] whitespace-nowrap">{{ $f['name'] }}</td>
                                    <td class="px-5 py-3 text-sm text-[#64748b] whitespace-nowrap">{{ $file['date'] }}</td>
                                    <td class="px-5 py-3 text-sm text-[#64748b] whitespace-nowrap">{{ $file['source'] }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-3 text-sm font-semibold text-[#2563eb]">
                                            @if(!empty($file['document']))
                                                <a href="{{ route('documents.download', $file['document']->id) }}" target="_blank" class="hover:text-[#1d4ed8]">View</a>
                                                <a href="{{ route('documents.download', $file['document']->id) }}" class="hover:text-[#1d4ed8]">Download</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.panel>
    </div>

    {{-- ── Scan / Upload modal (real file pick → AI classify → real save) ──── --}}
    <template x-teleport="body">
        <div x-show="scanOpen" x-cloak class="fixed inset-0 z-[999999] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="scanOpen = false"></div>
            <div class="relative w-full max-w-xl bg-white rounded-[20px] shadow-2xl overflow-hidden"
                 @click.stop
                 x-data="docScan({ recognizeUrl: '{{ route('ai.recognize-document') }}', clientName: @js($client->first_name.' '.$client->last_name) })">
                <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="documentable_type" value="Client">
                    <input type="hidden" name="documentable_id" value="{{ $client->id }}">

                    <div class="px-7 py-5 border-b border-[#eef2f9] flex justify-between items-center">
                        <h3 class="text-lg font-bold text-[#0f172a]">Scan / Upload Document</h3>
                        <button type="button" @click="scanOpen = false" class="w-8 h-8 rounded-full border border-[#eef2f9] flex items-center justify-center text-[#94a3b8] hover:bg-[#f8fafc]">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>

                    <div class="p-7 space-y-4 max-h-[70vh] overflow-y-auto">
                        {{-- File row --}}
                        <input type="file" name="file" x-ref="docFile" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.gif" @change="pickFile($event)">
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-card-border bg-card px-4 py-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="w-9 h-9 rounded-lg bg-[#dbe7fa] text-[#2563eb] flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                </span>
                                <div class="min-w-0">
                                    <div class="text-sm font-bold text-[#0f172a] truncate" x-text="fileName || 'No file selected'"></div>
                                    <div class="text-xs text-[#94a3b8]" x-text="fileMeta"></div>
                                </div>
                            </div>
                            <button type="button" @click="$refs.docFile.click()" class="inline-flex items-center gap-1.5 cursor-pointer text-sm font-semibold text-[#475569] border border-[#d8e2f0] rounded-[8px] px-3 py-1.5 hover:border-[#94a3b8]">
                                <span x-text="file ? 'Replace' : 'Choose file'"></span>
                            </button>
                        </div>

                        {{-- Reading / detected / states --}}
                        <div x-show="loading" x-cloak class="flex items-center gap-2.5 rounded-xl border border-[#cdddf5] bg-[#e8f0fc] px-4 py-3">
                            <svg class="w-4 h-4 text-[#2563eb] animate-spin shrink-0" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                            <p class="text-sm text-[#3b6cc4] font-semibold">Reading the document…</p>
                        </div>

                        <div x-show="detected?.document_type" x-cloak class="flex items-start gap-2.5 rounded-xl border border-[#cdddf5] bg-[#e8f0fc] px-4 py-3">
                            <svg class="w-4 h-4 text-[#2563eb] shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2z"/><path d="M12 8v4M12 16h.01"/></svg>
                            <div class="min-w-0">
                                <p class="text-sm text-[#3b6cc4]">Detected: <span class="font-bold text-[#1d4ed8]" x-text="detected?.document_type"></span> <span x-show="detected?.confidence">— <span x-text="detected?.confidence"></span> confidence</span></p>
                                <p x-show="detected?.summary" class="text-xs text-[#3b6cc4] mt-0.5" x-text="detected?.summary"></p>
                                <p x-show="detected?.suggested_status" class="text-xs text-[#1d4ed8] font-semibold mt-1">Suggests status → <span x-text="detected?.suggested_status"></span> · review on the Status tab before applying.</p>
                            </div>
                        </div>

                        <div x-show="detected?.unavailable" x-cloak class="flex items-start gap-2.5 rounded-xl border border-[#fdecc8] bg-[#fffaf0] px-4 py-3">
                            <svg class="w-4 h-4 text-[#b45309] shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2z"/><path d="M12 8v4M12 16h.01"/></svg>
                            <p class="text-sm text-[#b45309]">Auto-classify isn't switched on yet (needs the Claude API key). You can still name and upload the file manually.</p>
                        </div>

                        <div x-show="detected?.unsupported" x-cloak class="flex items-start gap-2.5 rounded-xl border border-[#e2e8f0] bg-[#f8fafc] px-4 py-3">
                            <svg class="w-4 h-4 text-[#94a3b8] shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2z"/><path d="M12 8v4M12 16h.01"/></svg>
                            <p class="text-sm text-[#64748b]">This file type can't be auto-read (only PDF/JPG/PNG). Name it and upload manually.</p>
                        </div>

                        <p x-show="error" x-cloak x-text="error" class="text-sm font-semibold text-[#d92d20]"></p>

                        <div>
                            <label class="block text-xs font-semibold text-[#94a3b8] uppercase tracking-wide mb-1.5">Document name <span class="text-[#dc2626]">*</span></label>
                            <input type="text" name="name" x-model="docName" required placeholder="e.g. Prior Authorization — Jane Doe" class="w-full px-3.5 py-2.5 rounded-[9px] border border-card-border bg-white text-sm outline-none focus:border-[#2563eb]">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-[#94a3b8] uppercase tracking-wide mb-1.5">Suggested folder</label>
                                <div class="relative">
                                    <select x-model="folder" class="w-full px-3.5 py-2.5 rounded-[9px] border border-card-border bg-white text-sm outline-none focus:border-[#2563eb] appearance-none pr-9 cursor-pointer">
                                        @foreach($folderNames as $fn)<option>{{ $fn }}</option>@endforeach
                                        <option>General</option>
                                    </select>
                                    <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-[#94a3b8] uppercase tracking-wide mb-1.5">Client</label>
                                <input type="text" value="{{ $client->first_name }} {{ $client->last_name }}" readonly class="w-full px-3.5 py-2.5 rounded-[9px] border border-card-border bg-card text-sm text-[#64748b] outline-none cursor-not-allowed">
                            </div>
                        </div>
                        <div x-show="detected?.summary" x-cloak>
                            <label class="block text-xs font-semibold text-[#94a3b8] uppercase tracking-wide mb-1.5">AI reading <span class="font-normal normal-case text-[#cbd5e1]">(for review — verified by you)</span></label>
                            <textarea rows="2" x-model="notes" readonly class="w-full px-3.5 py-2.5 rounded-[9px] border border-card-border bg-[#f8fafc] text-sm text-[#64748b] outline-none"></textarea>
                        </div>
                    </div>

                    <div class="px-7 py-4 border-t border-[#eef2f9] flex items-center justify-between">
                        <x-ui.btn variant="outline" type="button" x-on:click="scanOpen = false">Cancel</x-ui.btn>
                        <button type="submit" :disabled="!file"
                            :class="file ? 'bg-[#2563eb] hover:bg-[#1d4ed8] text-white' : 'bg-[#e2e8f0] text-[#94a3b8] cursor-not-allowed'"
                            class="px-5 py-2 rounded-[9px] text-sm font-semibold transition-colors inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Save document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    {{-- ── New folder modal ───────────────────────────────────────────────── --}}
    <template x-teleport="body">
        <div x-show="newFolderOpen" x-cloak class="fixed inset-0 z-[999999] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="newFolderOpen = false"></div>
            <div class="relative w-full max-w-md bg-white rounded-[20px] shadow-2xl overflow-hidden" @click.stop>
                <div class="px-7 py-5 border-b border-[#eef2f9] flex justify-between items-center">
                    <h3 class="text-base font-bold text-[#0f172a]">New folder</h3>
                    <button type="button" @click="newFolderOpen = false" class="w-8 h-8 rounded-full border border-[#eef2f9] flex items-center justify-center text-[#94a3b8] hover:bg-[#f8fafc]">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="p-7 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-[#94a3b8] uppercase tracking-wide mb-1.5">Folder name</label>
                        <input type="text" x-model="newFolderName" placeholder="e.g. Hospital records" class="w-full px-3.5 py-2.5 rounded-[9px] border border-card-border bg-white text-sm outline-none focus:border-[#2563eb] placeholder-[#94a3b8]">
                    </div>
                    <div class="flex justify-end gap-2.5">
                        <x-ui.btn variant="outline" type="button" x-on:click="newFolderOpen = false">Cancel</x-ui.btn>
                        <x-ui.btn variant="primary" type="button" x-on:click="newFolderOpen = false; newFolderName = ''">Create folder</x-ui.btn>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

@once
@push('scripts')
<script>
    // Scan/Upload modal: real file pick → Claude document recognition → pre-fill + real save.
    function docScan(cfg) {
        return {
            file: null, fileName: '', fileMeta: 'PDF, JPG or PNG — auto-classified on upload',
            loading: false, error: '', detected: null,
            docName: '', notes: '', folder: 'General',
            pickFile(e) {
                const f = e.target.files && e.target.files[0];
                if (!f) return;
                this.file = f;
                this.fileName = f.name;
                this.fileMeta = Math.max(1, Math.round(f.size / 1024)) + ' KB';
                this.detected = null; this.error = '';
                this.docName = f.name.replace(/\.[^.]+$/, '');
                this.classify(f);
            },
            classify(f) {
                if (!/\.(pdf|jpe?g|png|webp)$/i.test(f.name)) { this.detected = { unsupported: true }; return; }
                this.loading = true; this.error = '';
                const fd = new FormData(); fd.append('file', f);
                fetch(cfg.recognizeUrl, {
                    method: 'POST', body: fd,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                })
                .then(async (r) => {
                    const b = await r.json().catch(() => ({}));
                    if (r.ok && b.ok) {
                        this.detected = b.result;
                        if (b.result.summary) this.notes = b.result.summary;
                        if (b.result.document_type && b.result.document_type !== 'Other') {
                            this.docName = b.result.document_type + ' — ' + cfg.clientName;
                            this.folder = this.suggestFolder(b.result.document_type);
                        }
                    } else if (r.status === 503) { this.detected = { unavailable: true }; }
                    else if (r.status === 422) { this.detected = { unsupported: true }; }
                    else { this.error = b.error || 'Could not classify this file.'; }
                })
                .catch(() => { this.error = 'Could not reach the classify service.'; })
                .finally(() => { this.loading = false; });
            },
            suggestFolder(type) {
                const m = {
                    'Prior Authorization': 'Authorizations', 'Approval Notice': 'Authorizations', 'Denial Notice': 'Authorizations',
                    'Medical Needs Form': 'Eligibility', 'DHS-390': 'Eligibility', 'MSA-4676': 'Eligibility', 'Assessment Notice': 'Eligibility',
                    'EOB': 'Billing — Claims & EOBs',
                };
                return m[type] || 'General';
            },
        };
    }
</script>
@endpush
@endonce
