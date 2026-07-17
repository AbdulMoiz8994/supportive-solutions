@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="visitReportsPage(@js([
    'rows' => $rows,
    'counters' => $counters,
    'filters' => $filters,
    'csrfToken' => $csrfToken,
]))">

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-[28px] font-extrabold text-[#0f172a] tracking-tight leading-tight">Visit Reports</h1>
            <p class="text-[13px] text-[#64748b] mt-1.5">EVV proof for every visit — clock times, location match, and billable status.</p>
        </div>
        <div class="flex items-center gap-2 text-[12px] text-[#64748b]">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#ecfdf3] text-[#067647] font-semibold">
                <span class="w-1.5 h-1.5 rounded-full bg-[#10b981]"></span>
                Visit/EVV Monitor Agent active
            </span>
        </div>
    </div>

    @if(session('success'))
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    @endif

    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3">
        @foreach($counters as $counter)
            @php
                $counterQuery = request()->except(['report_status', 'date_preset', 'date_from', 'date_to']);
                if (! empty($counter['date_preset'])) {
                    $counterQuery['date_preset'] = $counter['date_preset'];
                } else {
                    $counterQuery['date_preset'] = request('date_preset', $filters['date_preset'] ?? 'this_week');
                    if (($counterQuery['date_preset'] ?? '') === 'custom') {
                        $counterQuery['date_from'] = request('date_from', $filters['date_from'] ?? null);
                        $counterQuery['date_to'] = request('date_to', $filters['date_to'] ?? null);
                    }
                }
                if (! empty($counter['filter'])) {
                    $counterQuery['report_status'] = $counter['filter'];
                }
                $isTodayCounter = ($counter['date_preset'] ?? null) === 'today';
                $isActive = $isTodayCounter
                    ? (($filters['date_preset'] ?? '') === 'today' && empty($filters['report_status']))
                    : (($filters['report_status'] ?? null) === ($counter['filter'] ?? null) && ! empty($counter['filter']));
            @endphp
            <a href="{{ route('visit-reports', $counterQuery) }}"
               class="rounded-xl border border-[#e2e8f0] bg-white px-3.5 py-3 hover:border-[#2563eb] transition-colors {{ $isActive ? 'ring-2 ring-[#2563eb]/30 border-[#2563eb]' : '' }}">
                <div class="text-[11.5px] text-[#64748b] mb-1">{{ $counter['label'] }}</div>
                <div class="text-[20px] font-bold text-[#0f172a] leading-tight">{{ $counter['value'] }}</div>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('visit-reports') }}" class="rounded-2xl border border-[#e6eef9] bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-[11px] font-semibold text-[#64748b] mb-1">Date</label>
                <select name="date_preset" id="visit-date-preset" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]"
                        onchange="document.getElementById('visit-custom-dates').style.display = this.value === 'custom' ? 'flex' : 'none'">
                    <option value="today" @selected($filters['date_preset'] === 'today')>Today</option>
                    <option value="this_week" @selected($filters['date_preset'] === 'this_week')>This week</option>
                    <option value="custom" @selected($filters['date_preset'] === 'custom')>Custom</option>
                </select>
            </div>
            <div id="visit-custom-dates" class="flex flex-wrap gap-3 items-end" style="display: {{ ($filters['date_preset'] ?? '') === 'custom' ? 'flex' : 'none' }}">
                <div>
                    <label class="block text-[11px] font-semibold text-[#64748b] mb-1">From</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-[#64748b] mb-1">To</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
                </div>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-[#64748b] mb-1">Caregiver</label>
                <select name="employee_id" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px] min-w-[160px]">
                    <option value="">All caregivers</option>
                    @foreach($caregivers as $cg)
                        <option value="{{ $cg['id'] }}" @selected($filters['employee_id'] == $cg['id'])>{{ $cg['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-[#64748b] mb-1">Client</label>
                <select name="client_id" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px] min-w-[160px]">
                    <option value="">All clients</option>
                    @foreach($clients as $cl)
                        <option value="{{ $cl['id'] }}" @selected($filters['client_id'] == $cl['id'])>{{ $cl['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-[#64748b] mb-1">Status</label>
                <select name="report_status" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
                    @foreach($statusOptions as $opt)
                        <option value="{{ $opt['value'] }}" @selected($filters['report_status'] === $opt['value'])>{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <x-ui.btn variant="primary" size="sm" type="submit">Apply filters</x-ui.btn>
        </div>
    </form>

    <div class="rounded-2xl border border-[#e6eef9] bg-white overflow-hidden shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[13px]">
                <thead>
                    <tr class="border-b border-[#e2e8f0] bg-[#f8fbff] text-[11px] uppercase tracking-wide text-[#64748b]">
                        <th class="px-4 py-3 font-semibold">Caregiver</th>
                        <th class="px-4 py-3 font-semibold">Client</th>
                        <th class="px-4 py-3 font-semibold">Date</th>
                        <th class="px-4 py-3 font-semibold">Scheduled time</th>
                        <th class="px-4 py-3 font-semibold">Clock in</th>
                        <th class="px-4 py-3 font-semibold">Clock out</th>
                        <th class="px-4 py-3 font-semibold">Duration</th>
                        <th class="px-4 py-3 font-semibold">Location match</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr class="border-b border-[#f1f5f9] hover:bg-[#f8fbff]/60">
                            <td class="px-4 py-3 font-semibold text-[#0f172a]">{{ $row['caregiver'] }}</td>
                            <td class="px-4 py-3">{{ $row['client'] }}</td>
                            <td class="px-4 py-3">{{ $row['date'] }}</td>
                            <td class="px-4 py-3 text-[#64748b]">{{ $row['scheduled_time'] }}</td>
                            <td class="px-4 py-3">{{ $row['clock_in'] }}</td>
                            <td class="px-4 py-3">{{ $row['clock_out'] }}</td>
                            <td class="px-4 py-3">{{ $row['duration'] }}</td>
                            <td class="px-4 py-3">
                                @if($row['location_overridden'] ?? false)
                                    <span class="text-[#067647] font-semibold" title="Passed via human-approved location override">Yes (Approved Override)</span>
                                @elseif($row['location_match_bool'] === true)
                                    <span class="text-[#067647] font-semibold">Yes</span>
                                @elseif($row['location_match_bool'] === false)
                                    <span class="text-[#b91c1c] font-semibold">No</span>
                                @else
                                    <span class="text-[#94a3b8]">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @include('pages.visit-reports.partials.status-badge', ['status' => $row['status'], 'label' => $row['status_label']])
                            </td>
                            <td class="px-4 py-3">
                                <button type="button" @click="openDetail({{ $row['id'] }})"
                                        class="text-[12px] font-semibold text-[#2563eb] hover:underline">
                                    {{ $row['can_fix'] ? 'Fix / Approve' : 'View' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-12 text-center text-[#64748b]">No visits match your filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('pages.visit-reports.partials.detail-modal')
</div>

<script>
function visitReportsPage(initial) {
    return {
        rows: initial.rows,
        counters: initial.counters,
        csrfToken: initial.csrfToken,
        detailOpen: false,
        detail: null,
        loading: false,
        toast: null,
        correction: { field: 'actual_clock_out', proposed_time: '', reason: '' },
        locationOverride: { reason: '' },

        init() {
            const openId = new URLSearchParams(window.location.search).get('open');
            if (openId) {
                this.openDetail(openId);
            }
        },

        async openDetail(id) {
            this.loading = true;
            this.detailOpen = true;
            try {
                const res = await fetch(`/visit-reports/${id}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                this.detail = data.visit;
            } catch (e) {
                this.toast = 'Could not load visit details.';
            } finally {
                this.loading = false;
            }
        },

        async postAction(url, body = {}) {
            const form = new FormData();
            Object.entries(body).forEach(([k, v]) => form.append(k, v));
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                body: form,
            });
            const data = await res.json();
            if (!res.ok || !data.ok) throw new Error(data.message || 'Action failed');
            this.detail = data.visit;
            this.toast = data.message;
            // Keep table/counters in sync after Fix/Approve or Missed.
            this.syncRowFromDetail(data.visit);
            return data;
        },

        syncRowFromDetail(visit) {
            if (!visit?.id) return;
            const idx = this.rows.findIndex(r => Number(r.id) === Number(visit.id));
            if (idx >= 0) {
                this.rows[idx] = {
                    ...this.rows[idx],
                    clock_in: visit.clock_in,
                    clock_out: visit.clock_out,
                    duration: visit.duration,
                    location_match: visit.location_match,
                    location_match_bool: visit.location_match_bool,
                    location_overridden: visit.location_overridden,
                    status: visit.status,
                    status_label: visit.status_label,
                    billable: visit.billable,
                    can_fix: visit.can_fix,
                    can_approve_location: visit.can_approve_location,
                };
            }
            // Soft-refresh counters from visible rows when status filters are inactive.
            if (this.counters?.length) {
                const byStatus = {
                    complete: this.rows.filter(r => r.status === 'complete').length,
                    in_progress: this.rows.filter(r => r.status === 'in_progress').length,
                    missed: this.rows.filter(r => r.status === 'missed').length,
                    needs_review: this.rows.filter(r => r.status === 'needs_review').length,
                };
                this.counters = this.counters.map(c => {
                    if (c.key === 'today') return c;
                    if (byStatus[c.key] !== undefined) return { ...c, value: byStatus[c.key] };
                    return c;
                });
            }
        },

        async proposeCorrection() {
            if (!this.detail) return;
            try {
                await this.postAction(`/visit-reports/${this.detail.id}/propose-correction`, this.correction);
            } catch (e) { this.toast = e.message; }
        },

        async approveCorrection() {
            if (!this.detail) return;
            try {
                await this.postAction(`/visit-reports/${this.detail.id}/approve-correction`);
                // Full reload so counters stay exact vs server after approval.
                setTimeout(() => window.location.reload(), 600);
            } catch (e) { this.toast = e.message; }
        },

        async approveLocation() {
            if (!this.detail) return;
            if (!this.locationOverride.reason?.trim()) {
                this.toast = 'A reason is required to approve a location mismatch.';
                return;
            }
            try {
                await this.postAction(`/visit-reports/${this.detail.id}/approve-location`, this.locationOverride);
                this.locationOverride = { reason: '' };
                setTimeout(() => window.location.reload(), 600);
            } catch (e) { this.toast = e.message; }
        },

        async markMissed() {
            if (!this.detail || !confirm('Mark this visit as missed? A follow-up task will be created.')) return;
            try {
                await this.postAction(`/visit-reports/${this.detail.id}/mark-missed`);
                setTimeout(() => window.location.reload(), 600);
            } catch (e) { this.toast = e.message; }
        },

        mapEmbedHtml(detail) {
            const points = (detail?.map_points || []).filter(p => Number.isFinite(Number(p.lat)) && Number.isFinite(Number(p.lng)));
            if (!points.length) return '';

            const mapId = 'visit-map-' + String(detail.id || Date.now());
            // Defer Leaflet init until Alpine has injected the container.
            queueMicrotask(() => this.initVisitMap(mapId, points));

            return `<div id="${mapId}" class="w-full h-48 rounded-lg border border-[#e2e8f0] bg-[#f8fafc]"></div>`;
        },

        ensureLeaflet() {
            if (window.L) return Promise.resolve(window.L);
            if (this._leafletPromise) return this._leafletPromise;

            this._leafletPromise = new Promise((resolve, reject) => {
                if (!document.getElementById('leaflet-css')) {
                    const link = document.createElement('link');
                    link.id = 'leaflet-css';
                    link.rel = 'stylesheet';
                    link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                    document.head.appendChild(link);
                }

                if (document.getElementById('leaflet-js')) {
                    const wait = () => window.L ? resolve(window.L) : setTimeout(wait, 40);
                    wait();
                    return;
                }

                const script = document.createElement('script');
                script.id = 'leaflet-js';
                script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                script.onload = () => resolve(window.L);
                script.onerror = reject;
                document.head.appendChild(script);
            });

            return this._leafletPromise;
        },

        async initVisitMap(mapId, points) {
            try {
                const L = await this.ensureLeaflet();
                const el = document.getElementById(mapId);
                if (!el || el.dataset.mapReady === '1') return;
                el.dataset.mapReady = '1';

                const map = L.map(el, { zoomControl: true, attributionControl: true });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap',
                }).addTo(map);

                const colors = { home: '#2563eb', in: '#067647', out: '#b91c1c' };
                const bounds = [];

                points.forEach((point) => {
                    const lat = Number(point.lat);
                    const lng = Number(point.lng);
                    const color = colors[point.tone] || '#334155';
                    const marker = L.circleMarker([lat, lng], {
                        radius: 8,
                        color: '#fff',
                        weight: 2,
                        fillColor: color,
                        fillOpacity: 0.95,
                    }).addTo(map);
                    marker.bindPopup(`<strong>${point.label}</strong><br>${lat.toFixed(5)}, ${lng.toFixed(5)}`);
                    bounds.push([lat, lng]);
                });

                if (bounds.length === 1) {
                    map.setView(bounds[0], 15);
                } else {
                    map.fitBounds(bounds, { padding: [28, 28] });
                }

                setTimeout(() => map.invalidateSize(), 50);
            } catch (e) {
                console.warn('Visit map failed to load', e);
            }
        },
    };
}
</script>
@endsection
