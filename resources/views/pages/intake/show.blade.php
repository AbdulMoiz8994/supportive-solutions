@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Intake Details" />

    <div x-data="{
        showUpload: false,
        showLogCall: false,
        showSchedule: false,
        noteText: '',
        scheduleDate: '',
        callNote: ''
    }">

        <div class="p-6 bg-white rounded-xl dark:bg-white/[0.03] shadow-theme-xs">

            {{-- ─── Header ─────────────────────────────────────────────────────── --}}
            <div class="flex flex-col gap-5 mb-8 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex items-center justify-center w-16 h-16 text-xl font-bold bg-brand-500/10 text-brand-500 rounded-xl uppercase">
                        {{ substr($intake->first_name, 0, 1) }}{{ substr($intake->last_name, 0, 1) }}
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-800 dark:text-white/90">
                            {{ $intake->first_name }} {{ $intake->last_name }}
                        </h3>
                        <div class="flex items-center gap-2 mt-1">
                            @php $displayStatus = $intake->displayStatus(); @endphp
                            <span class="px-2 py-0.5 text-[10px] font-black uppercase tracking-widest rounded-full
                                {{ $displayStatus === 'Ineligible' ? 'bg-red-100 text-red-600' :
                                   ($displayStatus === 'Contacted' ? 'bg-blue-100 text-blue-600' :
                                   ($displayStatus === 'Converted' ? 'bg-green-100 text-green-600' :
                                   ($displayStatus === 'Pending'   ? 'bg-orange-100 text-orange-600' :
                                   'bg-green-100 text-green-600'))) }}">
                                {{ $displayStatus }}
                            </span>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Source: {{ $intake->source ?? 'N/A' }} | Created: {{ $intake->created_at->format('M d, Y') }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <a href="{{ route('intakes.print', $intake->id) }}" target="_blank"
                       class="px-4 py-2 text-xs font-bold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 flex items-center gap-2 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        Print Assessment
                    </a>
                    <a href="{{ route('intakes.download', $intake->id) }}"
                       class="px-4 py-2 text-xs font-bold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 flex items-center gap-2 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download Assessment
                    </a>
                    @if(!$intake->converted_client_id)
                        <form action="{{ route('intakes.convert', $intake->id) }}" method="POST"
                              onsubmit="return confirm('Convert this lead into a full client record?');">
                            @csrf
                            <button type="submit" 
                                    style="background-color: #039855 !important; color: white !important; display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 10px; font-weight: 800; border: none; cursor: pointer;"
                                    class="text-sm shadow-theme-xs hover:opacity-90 transition-all">
                                ✓ Convert to Client
                            </button>
                        </form>
                    @else
                        <a href="{{ route('clients.show', $intake->converted_client_id) }}"
                           style="background-color: #465fff !important; color: white !important; display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 10px; font-weight: 800; text-decoration: none;"
                           class="text-sm shadow-theme-xs hover:opacity-90">
                            View Client Chart →
                        </a>
                    @endif
                </div>
            </div>

            {{-- ─── Body Grid ──────────────────────────────────────────────────── --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                {{-- Left: Details --}}
                <div class="lg:col-span-2 space-y-8">

                    {{-- Lead Info --}}
                    <div>
                        <h4 class="mb-5 text-sm font-semibold text-gray-800 uppercase dark:text-white/90">Lead Information</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="p-4 border border-gray-100 rounded-xl dark:border-white/[0.05]">
                                <span class="text-xs font-bold text-gray-400 uppercase">Phone</span>
                                <p class="mt-1 font-medium text-gray-800 dark:text-white/90">{{ $intake->phone ?? 'N/A' }}</p>
                            </div>
                            <div class="p-4 border border-gray-100 rounded-xl dark:border-white/[0.05]">
                                <span class="text-xs font-bold text-gray-400 uppercase">Email</span>
                                <p class="mt-1 font-medium text-gray-800 dark:text-white/90">{{ $intake->email ?? 'N/A' }}</p>
                            </div>
                            <div class="p-4 border border-gray-100 rounded-xl dark:border-white/[0.05]">
                                <span class="text-xs font-bold text-gray-400 uppercase">Date of Birth</span>
                                <p class="mt-1 font-medium text-gray-800 dark:text-white/90">{{ $intake->dob?->format('M d, Y') ?? 'N/A' }}</p>
                            </div>
                            <div class="p-4 border border-gray-100 rounded-xl dark:border-white/[0.05]">
                                <span class="text-xs font-bold text-gray-400 uppercase">Lead Source</span>
                                <p class="mt-1 font-medium text-gray-800 dark:text-white/90">{{ $intake->source ?? 'N/A' }}</p>
                            </div>
                            @if($intake->member_id)
                                <div class="p-4 border border-gray-100 rounded-xl dark:border-white/[0.05]">
                                    <span class="text-xs font-bold text-gray-400 uppercase">Medicaid ID</span>
                                    <p class="mt-1 font-medium text-gray-800 dark:text-white/90">{{ $intake->member_id }}</p>
                                </div>
                            @endif
                            @if($intake->address)
                                <div class="p-4 border border-gray-100 rounded-xl dark:border-white/[0.05]">
                                    <span class="text-xs font-bold text-gray-400 uppercase">Address</span>
                                    <p class="mt-1 font-medium text-gray-800 dark:text-white/90">{{ $intake->address }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Eligibility & program (scan-first wizard result) --}}
                    @if($intake->eligibility_status || $intake->recommended_program)
                        <div>
                            <h4 class="mb-4 text-sm font-semibold text-gray-800 uppercase dark:text-white/90">Eligibility &amp; Program</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="p-4 border rounded-xl
                                    {{ $intake->eligibility_status === \App\Models\Intake::ELIGIBILITY_ELIGIBLE ? 'border-green-100 bg-green-50/60' :
                                       ($intake->eligibility_status === \App\Models\Intake::ELIGIBILITY_INELIGIBLE ? 'border-red-100 bg-red-50/60' : 'border-amber-100 bg-amber-50/60') }}">
                                    <span class="text-xs font-bold text-gray-400 uppercase">Eligibility screen</span>
                                    <p class="mt-1 font-semibold
                                        {{ $intake->eligibility_status === \App\Models\Intake::ELIGIBILITY_ELIGIBLE ? 'text-green-700' :
                                           ($intake->eligibility_status === \App\Models\Intake::ELIGIBILITY_INELIGIBLE ? 'text-red-700' : 'text-amber-700') }}">
                                        {{ match($intake->eligibility_status) {
                                            \App\Models\Intake::ELIGIBILITY_ELIGIBLE => 'Looks eligible',
                                            \App\Models\Intake::ELIGIBILITY_INELIGIBLE => 'Not eligible',
                                            default => 'Needs verification',
                                        } }}
                                    </p>
                                    @if($intake->eligibility_note)
                                        <p class="mt-1 text-xs text-gray-500">{{ $intake->eligibility_note }}</p>
                                    @endif
                                    @if($intake->eligibility_checked_at)
                                        <p class="mt-1 text-[11px] text-gray-400">Checked {{ $intake->eligibility_checked_at->format('M d, Y g:i A') }}</p>
                                    @endif
                                </div>
                                <div class="p-4 border border-gray-100 rounded-xl dark:border-white/[0.05]">
                                    <span class="text-xs font-bold text-gray-400 uppercase">Recommended program</span>
                                    <p class="mt-1 font-medium text-gray-800 dark:text-white/90">{{ $intake->recommended_program ?? '—' }}</p>
                                    @if($intake->mco_name)
                                        <p class="mt-1 text-xs text-gray-500">MCO: {{ $intake->mco_name }}</p>
                                    @endif
                                    @if($intake->coverageType)
                                        <p class="mt-1 text-xs text-gray-500">Profile program: {{ $intake->coverageType->name }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Notes --}}
                    <div>
                        <h4 class="mb-4 text-sm font-semibold text-gray-800 uppercase dark:text-white/90">Notes / Comments</h4>
                        <div class="p-5 border border-gray-100 rounded-xl dark:border-white/[0.05] bg-gray-50/30 dark:bg-transparent">
                            @if($intake->notes)
                                @foreach(explode("\n", $intake->notes) as $line)
                                    <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed py-0.5">{{ $line }}</p>
                                @endforeach
                            @else
                                <p class="text-sm text-gray-400 italic">No notes yet. Use Quick Actions to log call attempts or schedule an assessment.</p>
                            @endif
                        </div>
                    </div>

                    {{-- Digital Signature --}}
                    <div>
                        <h4 class="mb-4 text-sm font-semibold text-gray-800 uppercase dark:text-white/90">Clinical Assessment Signature</h4>
                        <div class="p-6 border border-gray-200 rounded-2xl dark:border-white/[0.05] bg-white dark:bg-white/[0.02]">
                            <p class="mb-4 text-xs text-gray-500">Sign below to certify the clinical assessment for this prospect.</p>
                            <div class="relative w-full h-48 bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-100 overflow-hidden">
                                <canvas id="signatureCanvas" class="w-full h-full cursor-crosshair" width="600" height="200"></canvas>
                            </div>
                            <div class="mt-4 flex gap-3">
                                <button id="clearBtn" class="px-4 py-2 text-xs font-bold text-gray-500 uppercase hover:text-gray-700 transition-colors border border-gray-200 rounded-lg">Clear</button>
                                <button id="saveSignatureBtn" class="px-6 py-2 text-xs font-bold text-white bg-brand-500 rounded-lg hover:bg-brand-600 shadow-theme-xs uppercase">Save Digital Signature</button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right: Documents + Actions --}}
                <div class="space-y-6">

                    {{-- Documents Panel --}}
                    <div class="p-5 border border-gray-100 rounded-xl dark:border-white/[0.05]">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="font-semibold text-gray-800 dark:text-white/90">Intake Documents</h4>
                            <span class="text-xs font-bold text-gray-400">{{ $intake->documents->count() }} file(s)</span>
                        </div>

                        @forelse($intake->documents as $doc)
                            <div class="flex items-center gap-3 p-3 mb-2 bg-gray-50 dark:bg-white/[0.02] border border-gray-100 dark:border-white/[0.05] rounded-lg">
                                <div class="flex-shrink-0 w-8 h-8 bg-brand-50 rounded-lg flex items-center justify-center">
                                    <svg class="w-4 h-4 text-brand-500" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"></path></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">{{ $doc->name }}</p>
                                    <span class="text-[10px] font-bold uppercase {{ $doc->verification_status === 'Verified' ? 'text-green-500' : 'text-orange-500' }}">{{ $doc->verification_status ?? 'Pending' }}</span>
                                </div>
                                @if($doc->path)
                                    <a href="{{ route('documents.download', $doc->id) }}" target="_blank" class="text-gray-400 hover:text-brand-500 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    </a>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-gray-400 italic mb-4">No documents uploaded yet.</p>
                        @endforelse

                        {{-- Upload Button --}}
                        <button @click="showUpload = true"
                                class="w-full mt-3 py-2.5 border-2 border-dashed border-gray-200 rounded-lg text-xs font-bold text-gray-500 hover:border-brand-400 hover:text-brand-500 hover:bg-brand-50/30 transition-all flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Upload ID / File
                        </button>
                    </div>

                    {{-- Quick Actions Panel --}}
                    <div class="p-5 bg-brand-500/5 rounded-xl border border-brand-500/10">
                        <h4 class="mb-4 font-semibold text-brand-600">⚡ Quick Actions</h4>
                        <div class="space-y-2">

                            {{-- Log Call --}}
                            <button @click="showLogCall = true"
                                    class="w-full text-left px-4 py-2.5 rounded-lg bg-white dark:bg-white/[0.03] border border-gray-100 dark:border-white/[0.05] hover:border-brand-300 hover:bg-brand-50/50 transition-all group">
                                <div class="flex items-center gap-3">
                                    <span class="text-lg">📞</span>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-700 dark:text-white/90 group-hover:text-brand-600">Log Call Attempt</p>
                                        <p class="text-xs text-gray-400">Record a contact/voicemail attempt</p>
                                    </div>
                                </div>
                            </button>

                            {{-- Schedule Assessment --}}
                            <button @click="showSchedule = true"
                                    class="w-full text-left px-4 py-2.5 rounded-lg bg-white dark:bg-white/[0.03] border border-gray-100 dark:border-white/[0.05] hover:border-brand-300 hover:bg-brand-50/50 transition-all group">
                                <div class="flex items-center gap-3">
                                    <span class="text-lg">📅</span>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-700 dark:text-white/90 group-hover:text-brand-600">Schedule Assessment</p>
                                        <p class="text-xs text-gray-400">Set evaluation appointment date</p>
                                    </div>
                                </div>
                            </button>

                            {{-- Mark Ineligible --}}
                            <form action="{{ route('intakes.mark-ineligible', $intake->id) }}" method="POST"
                                  onsubmit="return confirm('Mark this lead as INELIGIBLE? This cannot be undone.');">
                                @csrf
                                <button type="submit"
                                        class="w-full text-left px-4 py-2.5 rounded-lg bg-white dark:bg-white/[0.03] border border-gray-100 dark:border-white/[0.05] hover:border-red-300 hover:bg-red-50/50 transition-all group">
                                    <div class="flex items-center gap-3">
                                        <span class="text-lg">❌</span>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-700 dark:text-white/90 group-hover:text-red-600">Mark as Ineligible</p>
                                            <p class="text-xs text-gray-400">Close this lead — not a fit</p>
                                        </div>
                                    </div>
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- Status History placeholder --}}
                    <div class="p-5 border border-gray-100 rounded-xl dark:border-white/[0.05]">
                        <h4 class="mb-3 font-semibold text-gray-800 dark:text-white/90 text-sm">Lead Timeline</h4>
                        <div class="space-y-3">
                            <div class="flex items-start gap-3">
                                <div class="w-2 h-2 mt-1.5 rounded-full bg-brand-500 shrink-0"></div>
                                <div>
                                    <p class="text-xs font-bold text-gray-700">Lead Created</p>
                                    <p class="text-xs text-gray-400">{{ $intake->created_at->format('M d, Y — h:i A') }}</p>
                                </div>
                            </div>
                            @if($intake->converted_client_id)
                                <div class="flex items-start gap-3">
                                    <div class="w-2 h-2 mt-1.5 rounded-full bg-green-500 shrink-0"></div>
                                    <div>
                                        <p class="text-xs font-bold text-green-600">Converted to Client</p>
                                        <p class="text-xs text-gray-400">{{ $intake->updated_at->format('M d, Y') }}</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══ UPLOAD DOCUMENT MODAL ════════════════════════════════════════ --}}
        <div x-show="showUpload" class="fixed inset-0 z-99999 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm" x-cloak>
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md p-8" @click.away="showUpload = false">
                <h4 class="mb-6 text-lg font-bold text-gray-800 dark:text-white/90">Upload Document</h4>
                <form action="{{ route('intakes.upload-document', $intake->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-2">Document Name (optional)</label>
                            <input type="text" name="name" placeholder="e.g. Medicaid ID Card" class="w-full px-4 py-2.5 rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-white/[0.05] focus:ring-2 focus:ring-brand-500/20 outline-none text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-2">Select File *</label>
                            <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                   class="w-full px-4 py-2.5 rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-white/[0.05] text-sm file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-600 hover:file:bg-brand-100">
                            <p class="text-xs text-gray-400 mt-1">Max 10MB. PDF, JPG, PNG, DOC accepted.</p>
                        </div>
                    </div>
                    <div class="mt-8 flex gap-3">
                        <button type="button" @click="showUpload = false" class="flex-1 py-2.5 text-sm font-bold text-gray-500 bg-gray-50 rounded-lg hover:bg-gray-100 uppercase">Cancel</button>
                        <button type="submit" class="flex-1 py-2.5 text-sm font-bold text-white bg-brand-500 rounded-lg hover:bg-brand-600 uppercase">Upload</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ═══ LOG CALL MODAL ════════════════════════════════════════════════ --}}
        <div x-show="showLogCall" class="fixed inset-0 z-99999 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm" x-cloak>
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md p-8" @click.away="showLogCall = false">
                <h4 class="mb-2 text-lg font-bold text-gray-800 dark:text-white/90">📞 Log Call Attempt</h4>
                <p class="text-sm text-gray-400 mb-6">Add a note about this call attempt to the lead timeline.</p>
                <form action="{{ route('intakes.log-call', $intake->id) }}" method="POST">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-2">Call Note</label>
                        <textarea name="note" rows="3" placeholder="e.g. Left voicemail. Will follow up in 2 days."
                                  class="w-full px-4 py-2.5 rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-white/[0.05] focus:ring-2 focus:ring-brand-500/20 outline-none text-sm resize-none">Called — {{ now()->format('M d, Y h:i A') }}. </textarea>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <button type="button" @click="showLogCall = false" class="flex-1 py-2.5 text-sm font-bold text-gray-500 bg-gray-50 rounded-lg hover:bg-gray-100 uppercase">Cancel</button>
                        <button type="submit" class="flex-1 py-2.5 text-sm font-bold text-white bg-brand-500 rounded-lg hover:bg-brand-600 uppercase">Save Log</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ═══ SCHEDULE ASSESSMENT MODAL ═════════════════════════════════════ --}}
        <div x-show="showSchedule" class="fixed inset-0 z-99999 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm" x-cloak>
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md p-8" @click.away="showSchedule = false">
                <h4 class="mb-2 text-lg font-bold text-gray-800 dark:text-white/90">📅 Schedule Assessment</h4>
                <p class="text-sm text-gray-400 mb-6">Pick a date for the clinical evaluation visit. Status will be updated to "Contacted".</p>
                <form action="{{ route('intakes.schedule-assessment', $intake->id) }}" method="POST">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-2">Assessment Date *</label>
                        <input type="date" name="assessment_date" required min="{{ now()->format('Y-m-d') }}"
                               value="{{ now()->addDays(3)->format('Y-m-d') }}"
                               class="w-full px-4 py-2.5 rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-white/[0.05] focus:ring-2 focus:ring-brand-500/20 outline-none text-sm">
                    </div>
                    <div class="mt-6 flex gap-3">
                        <button type="button" @click="showSchedule = false" class="flex-1 py-2.5 text-sm font-bold text-gray-500 bg-gray-50 rounded-lg hover:bg-gray-100 uppercase">Cancel</button>
                        <button type="submit" class="flex-1 py-2.5 text-sm font-bold text-white bg-brand-500 rounded-lg hover:bg-brand-600 uppercase">Confirm Date</button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script src="{{ asset('js/signature.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof SignaturePad !== 'undefined') {
                const pad = new SignaturePad('signatureCanvas');
                document.getElementById('clearBtn').addEventListener('click', () => pad.clear());
                document.getElementById('saveSignatureBtn').addEventListener('click', async () => {
                    const signature = pad.getBase64();
                    const btn = document.getElementById('saveSignatureBtn');
                    btn.disabled = true;
                    btn.innerText = 'Saving...';
                    try {
                        const response = await fetch('{{ route('documents.signature.store') }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify({
                                signature: signature,
                                documentable_id: {{ $intake->id }},
                                documentable_type: 'Intake',
                                document_name: 'Signed Clinical Assessment'
                            })
                        });
                        const data = await response.json();
                        if (data.success) { alert('Signed!'); window.location.reload(); }
                    } catch (e) {
                        btn.disabled = false;
                        btn.innerText = 'Save Digital Signature';
                    }
                });
            }
        });
    </script>
@endsection
