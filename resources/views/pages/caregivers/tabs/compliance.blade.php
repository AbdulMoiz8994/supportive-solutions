@php
    $c = $caregiver;
    $forms = $c->complianceForms->sortBy('period')->values();
    $jsForms = $forms->map(fn($f) => [
        'id' => $f->id,
        'label' => $f->period_label,
        'status' => $f->status,
        'client' => optional($f->client)->first_name.' '.optional($f->client)->last_name,
        'service' => ($f->service_start?->format('M j').' – '.$f->service_end?->format('M j, Y')),
        'required' => $f->required_days_per_week,
        'days' => $f->days ?? [],
        'delivered' => $f->delivered_hours,
        'authorized' => $f->authorized_hours,
        'excluded' => $f->excluded_days,
        'note' => $f->exclusion_note,
        'wellness' => $f->wellness_call_note,
        'submitted_at' => $f->submitted_at?->format('F j, Y'),
        'submitted_via' => $f->submitted_via,
    ]);
    $defaultIdx = $forms->search(fn($f) => !empty($f->days));
    $defaultIdx = $defaultIdx === false ? max(0, $forms->count() - 1) : $defaultIdx;
    $acks = [
        'I personally provided all services and was physically present the entire time.',
        'All services followed the MDHHS-approved care plan; no hours outside the approved schedule.',
        'I did not claim hours for any day the client was hospitalized or unavailable.',
        'I did not request payment for services not performed.',
        'All entries were submitted accurately and truthfully.',
        "I reported changes in the client's condition / hospitalization in a timely manner.",
        'I understand false claims may result in termination, repayment, and legal action.',
        'HHAeXchange clock-in/out completed.'.($c->evv_exempt ? ' (Exempt — live-in)' : ''),
    ];
@endphp
<div x-data="{ forms: @js($jsForms), sel: {{ $defaultIdx }} }">
    <div class="flex items-center gap-2 mb-4 text-[11px] font-bold text-[#64748b] flex-wrap">
        @foreach(['Wellness call (AI · '.($c->preferred_language ?? 'English').')','Submit form in app','Prorate days not worked','~10-day pay grace','Payroll (1st Tue)'] as $i => $stepLabel)
            <span class="flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-[10px]">{{ $i+1 }}</span> {{ $stepLabel }}</span>
            @if(!$loop->last)<span class="text-[#cbd5e1]">›</span>@endif
        @endforeach
    </div>

    {{-- Month strip --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-5">
        <template x-for="(f, i) in forms" :key="f.id">
            <button @click="sel = i" :class="sel === i ? 'border-blue-400 ring-2 ring-blue-500/10' : 'border-[#e2e8f0]'"
                class="text-left bg-white rounded-2xl border p-4 transition-all">
                <p class="text-[13px] font-bold text-[#1e293b]" x-text="f.label"></p>
                <p class="text-[11px] text-[#94a3b8] mt-0.5" x-text="f.client"></p>
                <p class="text-[11px] font-bold mt-1.5"
                   :class="{'text-green-600': f.status==='Submitted' || f.status==='Verified','text-orange-500': f.status==='Due' || f.status==='Awaiting'}">
                   <span x-text="'● ' + f.status"></span>
                   <span x-show="f.excluded > 0" x-text="' · ' + f.excluded + ' off'"></span>
                </p>
            </button>
        </template>
    </div>

    <template x-if="forms.length">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- Daily visit log --}}
        <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[14px] font-bold text-[#1e293b]"><span x-text="forms[sel].label"></span> — Daily Visit Log · <span x-text="forms[sel].client"></span></h3>
                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold" :class="(forms[sel].status==='Submitted' || forms[sel].status==='Verified') ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-600'" x-text="(forms[sel].status === 'Submitted' || forms[sel].status === 'Verified') ? (forms[sel].status + ' ' + (forms[sel].submitted_at||'')) : forms[sel].status"></span>
            </div>
            <div class="grid grid-cols-2 gap-3 mb-4">
                @include('pages.caregivers.tabs._kv', ['label'=>'Client','value'=>''])
                @include('pages.caregivers.tabs._kv', ['label'=>'Service Dates','value'=>''])
            </div>
            <div class="space-y-1.5 mb-3">
                <label class="text-[10px] font-black text-[#94a3b8] uppercase tracking-wider">Required Days / Week</label>
                <div class="px-4 py-2.5 bg-white border border-[#e2e8f0] rounded-xl text-[12px] font-semibold text-[#1e293b]"><span x-text="forms[sel].required"></span> days / week · any days (count, from the Time &amp; Task)</div>
            </div>
            <p class="text-[11px] text-[#94a3b8] mb-3">Mark the days actually worked. Compliance is whether the required count of days per week was met — the specific days don't matter.</p>
            <div class="flex items-center gap-4 text-[11px] font-semibold mb-3">
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-green-300"></span> Worked</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-gray-200"></span> Not worked</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-red-300"></span> Excluded (hospital)</span>
            </div>
            <template x-if="forms[sel].days.length">
                <div class="grid grid-cols-7 gap-1.5">
                    <template x-for="d in forms[sel].days" :key="d.day">
                        <div class="aspect-square rounded-lg flex flex-col items-center justify-center text-[11px] font-bold border"
                            :class="{'bg-green-50 border-green-200 text-green-700': d.state==='worked','bg-gray-50 border-gray-200 text-gray-400': d.state==='not','bg-red-50 border-red-200 text-red-600': d.state==='excluded'}">
                            <span x-text="d.day"></span>
                            <span x-show="d.state==='excluded'" class="text-[8px]">hosp.</span>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="!forms[sel].days.length">
                <div class="bg-orange-50/60 border border-orange-200 rounded-xl px-4 py-6 text-center text-[12px] font-semibold text-orange-600">Form not yet submitted for this month.</div>
            </template>
            <div x-show="forms[sel].note" class="mt-4 bg-orange-50/60 border border-orange-200 rounded-xl px-4 py-3 text-[11px] text-orange-700" x-text="'Comments: ' + forms[sel].note"></div>
        </div>

        {{-- Schedule check + proration --}}
        <div class="space-y-5">
            <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6">
                <h3 class="text-[14px] font-bold text-[#1e293b] mb-4">Schedule Check &amp; Proration</h3>
                <div class="space-y-2.5 text-[12px]">
                    <div class="flex justify-between"><span class="text-blue-600 font-medium">Required days / week</span><span class="font-bold text-[#1e293b]"><span x-text="forms[sel].required"></span> · any days</span></div>
                    <div class="flex justify-between"><span class="text-blue-600 font-medium">Weekly requirement</span><span class="font-bold text-green-600">Met every week ✓</span></div>
                    <div class="flex justify-between"><span class="text-blue-600 font-medium">Excluded (hospital)</span><span class="font-bold text-[#1e293b]" x-text="forms[sel].excluded > 0 ? (forms[sel].excluded + ' days') : 'None'"></span></div>
                    <div class="flex justify-between"><span class="text-blue-600 font-medium">Authorized</span><span class="font-bold text-[#1e293b]" x-text="(forms[sel].authorized||'—') + ' hrs'"></span></div>
                    <div class="flex justify-between border-t border-[#f1f5f9] pt-2.5 mt-2.5"><span class="text-blue-600 font-bold">Delivered / billable</span><span class="font-black text-[#1e293b]" x-text="(forms[sel].delivered||'—') + ' hrs'"></span></div>
                </div>
                <div class="mt-4 bg-blue-50/60 border border-blue-100 rounded-xl px-4 py-3 text-[11px] text-blue-700/90">
                    <b>Compliant — no proration penalty.</b> The requirement is a count of days per week worked on any days. As long as the weekly count is met, days off don't reduce anything; a week that falls short can be exempted (e.g. hospitalization).
                </div>
            </div>

            <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6">
                <h3 class="text-[14px] font-bold text-[#1e293b] mb-4">Submission &amp; Wellness Call</h3>
                <div class="space-y-3">
                    @include('pages.caregivers.tabs._kv', ['label'=>'Submitted By · Via','value'=>''])
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-[#94a3b8] uppercase tracking-wider">Date Submitted</label>
                        <div class="px-4 py-2.5 bg-white border border-[#e2e8f0] rounded-xl text-[12px] font-semibold text-[#1e293b]" x-text="forms[sel].submitted_at || '—'"></div>
                    </div>
                    <div x-show="forms[sel].wellness" class="bg-blue-50/60 border border-blue-100 rounded-xl px-4 py-3 text-[11px] text-blue-700/90" x-text="forms[sel].wellness"></div>
                    <div class="flex gap-2 pt-1">
                        <form method="POST" action="{{ route('caregivers.notes.store', $c->id) }}">@csrf
                            <input type="hidden" name="tag" value="Activity">
                            <input type="hidden" name="body" value="Triggered end-of-month wellness call.">
                            <button class="px-4 py-2 bg-white border border-[#e2e8f0] rounded-lg text-[11px] font-bold text-[#475569]">📞 Trigger call</button>
                        </form>
                        <button class="px-4 py-2 bg-[#2563eb] text-white rounded-lg text-[11px] font-bold">Upload form</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </template>
    <template x-if="!forms.length">
        <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-8 text-center text-[13px] font-semibold text-[#64748b]">No compliance forms on file for this caregiver yet.</div>
    </template>

    {{-- Acknowledgments --}}
    <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6 mt-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-[14px] font-bold text-[#1e293b]">Acknowledgments</h3>
            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-green-100 text-green-700">All initialed · YH</span>
        </div>
        <div class="space-y-2.5">
            @foreach($acks as $i => $ack)
            <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg border border-[#f1f5f9]">
                <span class="w-7 h-7 rounded-md flex items-center justify-center text-[10px] font-bold {{ $i === 7 && $c->evv_exempt ? 'bg-gray-100 text-gray-500' : 'bg-green-100 text-green-700' }}">{{ $i === 7 && $c->evv_exempt ? 'N/A' : 'YH' }}</span>
                <span class="text-[12px] text-[#475569]">{{ $ack }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

