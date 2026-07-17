<div class="rounded-2xl border border-[#e2e8f0] bg-white p-5 shadow-sm">
    <h3 class="text-[14px] font-bold text-[#0f172a] mb-3">Log call or case note</h3>
    <form method="POST" action="{{ route('communications.manual.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-3">
        @csrf
        <select name="channel" class="rounded-xl border border-[#e2e8f0] bg-white px-3 py-2.5 text-[13px] text-[#0f172a]">
            <option value="call">Call log</option>
            <option value="note">Case note</option>
        </select>
        <select name="direction" class="rounded-xl border border-[#e2e8f0] bg-white px-3 py-2.5 text-[13px] text-[#0f172a]">
            <option value="inbound">Inbound</option>
            <option value="outbound">Outbound</option>
        </select>
        <input type="text" name="subject" placeholder="Subject (optional)" class="rounded-xl border border-[#e2e8f0] bg-white px-3 py-2.5 text-[13px] md:col-span-2">
        <textarea name="body" rows="3" required placeholder="Summary" class="rounded-xl border border-[#e2e8f0] bg-white px-3 py-2.5 text-[13px] md:col-span-2"></textarea>
        <select name="related_type" class="rounded-xl border border-[#e2e8f0] bg-white px-3 py-2.5 text-[13px]">
            <option value="">No related record</option>
            <option value="Client">Client</option>
            <option value="Employee">Caregiver / Employee</option>
        </select>
        <input type="number" name="related_id" placeholder="Related record ID" class="rounded-xl border border-[#e2e8f0] bg-white px-3 py-2.5 text-[13px]">
        <button type="submit" class="md:col-span-2 rounded-xl bg-[#2563eb] text-white text-[13px] font-semibold px-4 py-2.5 hover:bg-[#1d4ed8] transition">Save log entry</button>
    </form>
</div>
