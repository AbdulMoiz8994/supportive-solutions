@php
    $c = $caregiver;
    $active = $c->assignments->where('status', 'Active');
    $primary = $assignment;
    $authHours = $active->sum('authorized_hours');
    $clientsForSelect = \App\Models\Client::orderBy('first_name')->get(['id','first_name','last_name']);
@endphp

{{-- KPI row --}}
<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-5">
    @php $stats = [
        ['Clients served', $active->count(), $active->count().' active assignment'.($active->count()===1?'':'s'), 'text-[#1e293b]'],
        ['Authorized hours / mo', $authHours, '≈'.round($authHours/4.3).' hrs/week', 'text-[#1e293b]'],
        ['Live-in', $c->live_in ? 'Yes' : 'No', $c->evv_exempt ? 'EVV exempt' : 'On HHAeXchange', 'text-[#1e293b]'],
        ['Assignment status', $c->status, $primary?->assigned_since ? 'Since '.$primary->assigned_since->format('M j, Y') : '—', 'text-green-600'],
    ]; @endphp
    @foreach($stats as [$label, $value, $sub, $color])
    <div class="bg-white rounded-[18px] border border-[#e2e8f0] p-5">
        <p class="text-[11px] font-bold text-[#94a3b8]">{{ $label }}</p>
        <p class="text-[26px] font-black {{ $color }} leading-none mt-1.5">{{ $value }}</p>
        <p class="text-[11px] text-[#94a3b8] mt-1.5">{{ $sub }}</p>
    </div>
    @endforeach
</div>

<h3 class="text-[14px] font-bold text-[#1e293b] mb-3">Current Assignment</h3>
@if($primary && $primary->client)
<div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6 mb-5">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-5 border-b border-[#f1f5f9]">
        <div class="flex items-center gap-3">
            <img src="https://ui-avatars.com/api/?name={{ urlencode($primary->client->first_name.' '.$primary->client->last_name) }}&background=dbeafe&color=1e3a8a&bold=true" class="w-12 h-12 rounded-full">
            <div>
                <div class="flex items-center gap-2">
                    <h4 class="text-[16px] font-bold text-[#1e293b]">{{ $primary->client->first_name }} {{ $primary->client->last_name }}</h4>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700">Active</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-100 text-violet-700">{{ $primary->program ?? 'MICH' }}</span>
                    @if($primary->live_in)<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-100 text-orange-700">Live-in</span>@endif
                </div>
                <p class="text-[12px] text-[#94a3b8] mt-0.5">{{ $primary->relationship }} · {{ $primary->program ?? 'MICH' }}</p>
            </div>
        </div>
        <a href="{{ route('clients.show', $primary->client->id) }}" class="px-4 py-2 bg-[#2563eb] text-white rounded-xl text-[12px] font-bold shadow-lg shadow-blue-100">Open client profile →</a>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-5 pt-5">
        @include('pages.caregivers.tabs._kv', ['label'=>'Relationship','value'=>$primary->relationship])
        @include('pages.caregivers.tabs._kv', ['label'=>'Authorized','value'=>$primary->authorized_hours ? $primary->authorized_hours.' hrs/mo (T1019)' : '—'])
        @include('pages.caregivers.tabs._kv', ['label'=>'Scheduled','value'=>$primary->scheduled_hours ? '≈'.(int)$primary->scheduled_hours.' hrs/wk' : '—'])
        @include('pages.caregivers.tabs._kv', ['label'=>'Assigned Since','value'=>$primary->assigned_since?->format('M j, Y')])
        @include('pages.caregivers.tabs._kv', ['label'=>'Authorization','value'=>$primary->authorization_no])
        @include('pages.caregivers.tabs._kv', ['label'=>'EVV','value'=>$primary->evv_status])
        <div class="space-y-1.5">
            <label class="text-[10px] font-black text-[#94a3b8] uppercase tracking-wider">This Month's Compliance</label>
            <div class="px-4 py-2.5 bg-orange-50 border border-orange-200 rounded-xl text-[12px] font-bold text-orange-600">{{ $primary->compliance_status ?? '—' }}</div>
        </div>
        @include('pages.caregivers.tabs._kv', ['label'=>'Relationship','value'=>'End assignment'])
    </div>
    <div class="mt-5 bg-blue-50/60 border border-blue-100 rounded-xl px-5 py-3.5">
        <p class="text-[12px] text-blue-700/90"><b>Multiple clients supported.</b> A caregiver can be assigned to more than one client; combined scheduled hours can't exceed each client's authorized total, and each assignment tracks its own compliance form and (if applicable) EVV.</p>
    </div>
</div>
@else
<div class="bg-white rounded-[20px] border border-[#e2e8f0] p-10 text-center text-[#94a3b8] italic mb-5">No active assignment yet.</div>
@endif

{{-- Assign another client (working form) --}}
<div class="bg-[#eff6ff] rounded-[20px] border border-blue-100/50 p-6">
    <div class="flex items-center justify-between mb-5">
        <h3 class="text-[15px] font-bold text-[#1e293b]">Assign Another Client</h3>
        <span class="text-[12px] text-[#94a3b8]">Add a second client this caregiver serves</span>
    </div>
    <form method="POST" action="{{ route('caregivers.assignments.store', $c->id) }}" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
        @csrf
        <div class="space-y-1.5">
            <label class="text-[10px] font-black text-[#94a3b8] uppercase">Client</label>
            <select name="client_id" required class="w-full px-3 py-2.5 bg-white border border-[#e2e8f0] rounded-xl text-[12px] font-semibold outline-none focus:ring-2 focus:ring-blue-500/10">
                <option value="">Search clients…</option>
                @foreach($clientsForSelect as $cl)<option value="{{ $cl->id }}">{{ $cl->first_name }} {{ $cl->last_name }}</option>@endforeach
            </select>
        </div>
        <div class="space-y-1.5">
            <label class="text-[10px] font-black text-[#94a3b8] uppercase">Relationship</label>
            <select name="relationship" class="w-full px-3 py-2.5 bg-white border border-[#e2e8f0] rounded-xl text-[12px] font-semibold outline-none focus:ring-2 focus:ring-blue-500/10">
                <option value="">Select…</option>
                @foreach(['Mother','Father','Wife','Husband','Son','Daughter','Uncle','Aunt','Other'] as $r)<option>{{ $r }}</option>@endforeach
            </select>
        </div>
        <div class="space-y-1.5">
            <label class="text-[10px] font-black text-[#94a3b8] uppercase">Scheduled hrs/wk</label>
            <input type="number" name="scheduled_hours" step="0.5" placeholder="—" class="w-full px-3 py-2.5 bg-white border border-[#e2e8f0] rounded-xl text-[12px] font-semibold outline-none focus:ring-2 focus:ring-blue-500/10">
        </div>
        <div class="space-y-1.5">
            <label class="text-[10px] font-black text-[#94a3b8] uppercase">Live-in?</label>
            <select name="live_in" class="w-full px-3 py-2.5 bg-white border border-[#e2e8f0] rounded-xl text-[12px] font-semibold outline-none focus:ring-2 focus:ring-blue-500/10">
                <option value="0">No</option><option value="1">Yes</option>
            </select>
        </div>
        <button type="submit" class="px-5 py-2.5 bg-[#2563eb] text-white rounded-xl text-[12px] font-bold shadow-lg shadow-blue-100 hover:bg-[#1d4ed8]">+ Assign</button>
    </form>
</div>

{{-- Assignment history --}}
<div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6 mt-5">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-[15px] font-bold text-[#1e293b]">Assignment History</h3>
        <span class="text-[11px] font-bold text-[#94a3b8]">All clients served</span>
    </div>
    <table class="w-full text-[12px]">
        <thead><tr class="text-[10px] font-black text-[#94a3b8] uppercase border-b border-[#f1f5f9]">
            <th class="py-2 text-left">Client</th><th class="py-2 text-left">Relationship</th><th class="py-2 text-left">Program</th><th class="py-2 text-left">Period</th><th class="py-2 text-left">Hours/mo</th><th class="py-2 text-left">Status</th>
        </tr></thead>
        <tbody class="divide-y divide-[#f1f5f9]">
            @forelse($c->assignments as $a)
            <tr>
                <td class="py-2.5 font-bold text-[#1e293b]">{{ optional($a->client)->first_name }} {{ optional($a->client)->last_name }}</td>
                <td class="py-2.5 text-[#475569]">{{ $a->relationship ?? '—' }}</td>
                <td class="py-2.5"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-50 text-violet-600">{{ $a->program ?? 'MICH' }}</span></td>
                <td class="py-2.5 text-[#475569]">{{ $a->assigned_since?->format('M j, Y') ?? '—' }} – {{ $a->ended_at?->format('M j, Y') ?? 'present' }}</td>
                <td class="py-2.5 text-[#475569]">{{ $a->authorized_hours ?? '—' }}</td>
                <td class="py-2.5"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $a->status === 'Active' ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-500' }}">{{ $a->status === 'Active' ? 'Current' : $a->status }}</span></td>
            </tr>
            @empty
            <tr><td colspan="6" class="py-6 text-center text-[#94a3b8] italic">No assignments yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

