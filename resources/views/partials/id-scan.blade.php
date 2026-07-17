{{--
    Scan-ID auto-fill (Claude vision). Renders a "Scan ID" button + a confirm
    modal. On confirm it dispatches a window `id-scanned` CustomEvent whose
    detail is the (staff-edited) extracted fields; the host form listens via
    x-on:id-scanned.window and maps the fields into its own model.

    Per the EMR spec the read is ALWAYS confirmed before anything is filled.
--}}
<div x-data="idScan()" class="inline-block">
    <input type="file" x-ref="idFile" accept="image/*" capture="environment" class="hidden" @change="onFile($event)">

    <button type="button" @click="$refs.idFile.click()" :disabled="loading"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-[#2563eb]/30 bg-[#eff4ff] text-[#2563eb] text-[12px] font-bold hover:bg-[#dbe6ff] transition disabled:opacity-60">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg>
        <span x-show="!loading">Scan ID</span>
        <span x-show="loading" x-cloak>Reading ID…</span>
    </button>
    <p x-show="error" x-cloak x-text="error" class="text-[11px] font-semibold text-[#d92d20] mt-1"></p>

    {{-- Confirm-before-fill modal --}}
    <div x-show="confirming" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4"
         @keydown.escape.window="confirming=false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6" @click.outside="confirming=false">
            <h3 class="text-base font-bold text-[#0f172a] mb-1">Confirm scanned details</h3>
            <p class="text-[12px] text-[#64748b] mb-4">Review what we read from the ID, fix anything that's off, then fill the form.</p>
            <div class="grid grid-cols-2 gap-3 max-h-[55vh] overflow-y-auto pr-1">
                <template x-for="f in scanKeys" :key="f.key">
                    <div :class="f.wide ? 'col-span-2' : ''">
                        <label class="block text-[10px] font-black text-[#94a3b8] uppercase tracking-wider mb-1" x-text="f.label"></label>
                        <input type="text" x-model="fields[f.key]"
                            class="w-full px-3 py-2 rounded-lg border border-[#e2e8f0] text-[13px] text-[#1e293b] outline-none focus:ring-2 focus:ring-blue-500/15">
                    </div>
                </template>
            </div>
            <div class="flex justify-end gap-2 mt-5">
                <button type="button" @click="confirming=false" class="px-4 py-2 rounded-xl border border-[#e2e8f0] text-[12px] font-bold text-[#475569] hover:bg-gray-50">Cancel</button>
                <button type="button" @click="apply()" class="px-5 py-2 rounded-xl bg-[#2563eb] text-white text-[12px] font-bold hover:bg-[#1d4ed8]">Confirm &amp; Fill</button>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
    // MM/DD/YYYY (or similar) → YYYY-MM-DD for <input type="date">. Returns '' if unparseable.
    window.idScanDob = function (s) {
        if (!s) return '';
        var m = String(s).match(/(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})/);
        if (m) {
            var y = m[3].length === 2 ? ('19' + m[3]) : m[3];
            return y + '-' + ('0' + m[1]).slice(-2) + '-' + ('0' + m[2]).slice(-2);
        }
        if (/^\d{4}-\d{2}-\d{2}/.test(s)) return String(s).slice(0, 10);
        return '';
    };
    // ID "sex" (M/F) → form gender label.
    window.idScanSex = function (s) {
        if (!s) return '';
        var c = String(s).trim().toUpperCase().charAt(0);
        return c === 'M' ? 'Male' : (c === 'F' ? 'Female' : '');
    };
    // Compose a single address line from the parts the host form needs.
    window.idScanAddress = function (d) {
        return [d.address, d.city, d.state, d.zip].filter(Boolean).join(', ');
    };

    function idScan() {
        return {
            loading: false, error: '', confirming: false, fields: {},
            scanKeys: [
                { key: 'first_name', label: 'First name' },
                { key: 'last_name', label: 'Last name' },
                { key: 'middle_name', label: 'Middle name' },
                { key: 'date_of_birth', label: 'Date of birth' },
                { key: 'sex', label: 'Sex' },
                { key: 'address', label: 'Street address', wide: true },
                { key: 'city', label: 'City' },
                { key: 'state', label: 'State' },
                { key: 'zip', label: 'ZIP' },
                { key: 'id_number', label: 'ID number' },
            ],
            async onFile(e) {
                var file = e.target.files && e.target.files[0];
                if (!file) return;
                this.loading = true; this.error = '';
                var fd = new FormData(); fd.append('image', file);
                try {
                    var r = await fetch("{{ route('ai.scan-id') }}", {
                        method: 'POST', body: fd,
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                            'Accept': 'application/json',
                        },
                    });
                    var data = await r.json();
                    if (!data.ok) { this.error = data.error || 'Could not read the ID.'; }
                    else { this.fields = data.result.fields || {}; this.confirming = true; }
                } catch (err) {
                    this.error = 'Could not reach the scan service.';
                }
                this.loading = false;
                if (e.target) e.target.value = '';
            },
            apply() {
                window.dispatchEvent(new CustomEvent('id-scanned', { detail: this.fields }));
                this.confirming = false;
            },
        };
    }
</script>
@endpush
@endonce
