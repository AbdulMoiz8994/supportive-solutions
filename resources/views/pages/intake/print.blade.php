@extends('layouts.fullscreen-layout')

@section('content')
    <div class="max-w-5xl mx-auto p-12 bg-white shadow-xl my-8 pb-48 print:shadow-none print:my-0 print:p-8 print:pb-0" style="font-family: 'Outfit', sans-serif; color: #111827;">
        
        <!-- ═══ OFFICIAL HEADER ══════════════════════════════════════════════ -->
        <table class="w-full mb-10 border-b-4 border-[#3641f5] pb-10">
            <tr>
                <td class="w-2/3">
                    <div class="flex items-center gap-5">
                        <div style="background-color: #3641f5 !important; color: white !important; width: 64px; height: 64px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">B</div>
                        <div>
                            <h2 class="text-3xl font-black uppercase tracking-tight" style="color: #111827;">BeydounTech</h2>
                            <p class="text-xs font-bold uppercase tracking-widest mt-1" style="color: #3641f5;">Clinical Home Care Services</p>
                            <p class="text-[10px] text-gray-500 uppercase mt-2">Confidential Clinical Assessment Record</p>
                        </div>
                    </div>
                </td>
                <td class="w-1/3 text-right">
                    <h1 class="text-4xl font-black text-gray-900 uppercase leading-none">INTAKE</h1>
                    <p class="text-sm font-bold text-gray-400 uppercase tracking-widest">Assessment Form</p>
                    <div class="mt-4 inline-block bg-gray-100 px-3 py-1 rounded text-[10px] font-black uppercase">
                        Ref: INT-{{ $intake->id }}-{{ date('Y') }}
                    </div>
                </td>
            </tr>
        </table>

        <!-- ═══ PATIENT INFORMATION TABLE ════════════════════════════════════ -->
        <div class="mb-10">
            <h3 class="bg-gray-900 text-white px-4 py-2 text-[11px] font-black uppercase tracking-[0.2em] mb-4">I. Patient Demographic Profile</h3>
            <table class="w-full border-collapse border border-gray-200">
                <tr class="border-b border-gray-200">
                    <td class="p-4 border-r border-gray-200 w-1/2">
                        <label class="text-[9px] font-black text-gray-400 uppercase block mb-1">Full Legal Name</label>
                        <p class="text-xl font-black text-gray-900">{{ $intake->first_name }} {{ $intake->last_name }}</p>
                    </td>
                    <td class="p-4 w-1/2">
                        <label class="text-[9px] font-black text-gray-400 uppercase block mb-1">Date of Birth</label>
                        <p class="text-xl font-black text-gray-800">{{ $intake->dob?->format('M d, Y') ?? 'Not Provided' }}</p>
                    </td>
                </tr>
                <tr class="border-b border-gray-200">
                    <td class="p-4 border-r border-gray-200">
                        <label class="text-[9px] font-black text-gray-400 uppercase block mb-1">Medicaid / Member ID</label>
                        <p class="text-lg font-bold text-gray-800 uppercase tracking-wide">{{ $intake->member_id ?? 'PENDING' }}</p>
                    </td>
                    <td class="p-4">
                        <label class="text-[9px] font-black text-gray-400 uppercase block mb-1">Primary Contact Phone</label>
                        <p class="text-lg font-bold text-gray-800">{{ $intake->phone }}</p>
                    </td>
                </tr>
                <tr>
                    <td class="p-4 border-r border-gray-200">
                        <label class="text-[9px] font-black text-gray-400 uppercase block mb-1">Source of Referral</label>
                        <p class="text-lg font-bold text-gray-800">{{ $intake->source ?? 'N/A' }}</p>
                    </td>
                    <td class="p-4">
                        <label class="text-[9px] font-black text-gray-400 uppercase block mb-1">Lead Status</label>
                        <p class="text-lg font-bold text-gray-800 uppercase">{{ $intake->status ?? 'New' }}</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ═══ CLINICAL NARRATIVE TABLE ═════════════════════════════════════ -->
        <div class="mb-10">
            <h3 class="bg-gray-900 text-white px-4 py-2 text-[11px] font-black uppercase tracking-[0.2em] mb-4">II. Clinical Narrative & Observations</h3>
            <div class="border border-gray-200 p-6 min-h-[160px] leading-relaxed">
                <label class="text-[9px] font-black text-gray-300 uppercase block mb-4 border-b border-gray-100 pb-2">Initial Assessment Findings</label>
                <div class="text-base text-gray-800 font-medium">
                    @if($intake->notes)
                        {!! nl2br(e($intake->notes)) !!}
                    @else
                        <p class="italic text-gray-400">No narrative assessment recorded at this time.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- ═══ ADMINISTRATIVE CHECKLIST ═════════════════════════════════════ -->
        <div class="mb-14">
            <h3 class="bg-gray-900 text-white px-4 py-2 text-[11px] font-black uppercase tracking-[0.2em] mb-4">III. Administrative Certification</h3>
            <table class="w-full border-collapse border border-gray-200">
                <tr class="border-b border-gray-200">
                    <td class="p-4 w-12 border-r border-gray-200 text-center"><div class="w-6 h-6 border-2 border-gray-400 rounded-md mx-auto"></div></td>
                    <td class="p-4 text-xs font-black uppercase tracking-wide text-gray-700 font-bold">Face to Face Documentation Received and Verified</td>
                </tr>
                <tr class="border-b border-gray-200">
                    <td class="p-4 w-12 border-r border-gray-200 text-center"><div class="w-6 h-6 border-2 border-gray-400 rounded-md mx-auto"></div></td>
                    <td class="p-4 text-xs font-black uppercase tracking-wide text-gray-700 font-bold">In-Home Clinical Assessment (PCA) Completed</td>
                </tr>
                <tr>
                    <td class="p-4 w-12 border-r border-gray-200 text-center"><div class="w-6 h-6 border-2 border-gray-400 rounded-md mx-auto"></div></td>
                    <td class="p-4 text-xs font-black uppercase tracking-wide text-gray-700 font-bold">Service Authorization (T019) Submitted for Approval</td>
                </tr>
            </table>
        </div>

        <!-- ═══ SIGNATURE BLOCKS ═════════════════════════════════════════════ -->
        <div class="mt-20 overflow-visible">
            <table class="w-full border-collapse">
                <tr>
                    <td class="w-1/2 pr-8 vertical-align-top">
                        <div class="border-t-2 border-gray-900 pt-3 h-24">
                            <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest block mb-1">Patient / Legal Guardian Signature</label>
                            <p class="text-[10px] font-bold text-gray-300 italic mb-10">Digitally Verified via Secure Portal</p>
                            <div class="flex justify-between items-end border-t border-gray-100 pt-1">
                                <span class="text-[10px] font-black text-gray-900 uppercase">DATE: ___ / ___ / ___</span>
                            </div>
                        </div>
                    </td>
                    <td class="w-1/2 pl-8 vertical-align-top">
                        <div class="border-t-2 border-gray-900 pt-3 h-24">
                            <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest block mb-1">Assessing Clinician Signature</label>
                            <p class="text-[11px] font-black text-gray-900 uppercase mb-1">
                                {{ auth()->user()->first_name }} {{ auth()->user()->last_name }}
                            </p>
                            <p class="text-[9px] font-bold text-gray-500 uppercase tracking-widest mb-4">Role: {{ auth()->user()->role }}</p>
                            <div class="flex justify-between items-end border-t border-gray-100 pt-1">
                                <span class="text-[10px] font-black text-gray-900 uppercase">DATE: {{ date('m / d / Y') }}</span>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ═══ FOOTER ═══════════════════════════════════════════════════════ -->
        <div class="mt-28 pt-8 border-t border-gray-100 text-center">
            <p class="text-[9px] font-black text-gray-400 uppercase tracking-[0.4em] mb-2">OFFICIAL CLINICAL RECORD — HIPAA PROTECTED</p>
            <p class="text-[8px] text-gray-300 tracking-widest leading-loose">
                © {{ date('Y') }} BEYDOUNTECH HOME CARE SERVICES.<br>
                GENERATED ON {{ date('M d, Y H:i A') }}
            </p>
        </div>
    </div>

    <!-- ═══ PRINT CONTROLS ═══════════════════════════════════════════════ -->
    <div class="fixed bottom-10 left-0 right-0 flex justify-center gap-6 print:hidden z-99999">
        <a href="{{ route('intakes.show', $intake->id) }}" class="px-8 py-3 bg-white text-gray-800 rounded-full font-bold shadow-2xl border border-gray-200 hover:bg-gray-100 transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Return to Profile
        </a>
        <button onclick="window.print()" class="px-10 py-3 bg-[#3641f5] text-white rounded-full font-black shadow-2xl hover:bg-brand-700 transform hover:scale-105 transition-all text-sm uppercase tracking-widest flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Print Final Copy
        </button>
    </div>
@endsection
