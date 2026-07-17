@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="dataExplorationPage(@js([
    'dataset' => $dataset,
    'config' => $config,
    'columns' => $columns,
    'rows' => $rows,
    'chart' => $chart,
    'truncated' => $truncated ?? false,
    'totalMatched' => $totalMatched ?? count($rows),
    'datasets' => $datasets,
    'groupByOptions' => $groupByOptions,
    'aggregateOptions' => $aggregateOptions,
    'filterFields' => $filterFields,
    'statusOptions' => $statusOptions ?? [],
    'datePresets' => $datePresets ?? [],
    'clients' => $clients,
    'caregivers' => $caregivers,
    'chartTypes' => $chartTypes,
    'savedViews' => $savedViews,
    'csrfToken' => $csrfToken,
]))" x-init="renderChart()">

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-[28px] font-extrabold text-[#0f172a] tracking-tight leading-tight">Data Exploration 2.0</h1>
            <p class="text-[13px] text-[#64748b] mt-1.5">Ask your own questions — filter, group, chart, and export. Read-only.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <div class="relative inline-block" x-data="{ open: false }">
                <button type="button" @click="open = !open"
                        class="inline-flex items-center px-3.5 py-2 rounded-xl border border-[#e2e8f0] text-[12px] font-semibold text-[#475569] hover:border-[#2563eb]">
                    Export
                </button>
                <div x-show="open" @click.outside="open = false" x-cloak
                     class="absolute right-0 mt-1 w-44 bg-white border border-[#e2e8f0] rounded-xl shadow-lg z-20 py-1">
                    <a :href="exportUrl('csv')" class="block px-4 py-2 text-[12px] text-[#334155] hover:bg-[#f8fafc]">Export CSV</a>
                    <a :href="exportUrl('xlsx')" class="block px-4 py-2 text-[12px] text-[#334155] hover:bg-[#f8fafc]">Excel (XLSX)</a>
                    <a :href="exportUrl('pdf')" class="block px-4 py-2 text-[12px] text-[#334155] hover:bg-[#f8fafc]">PDF</a>
                </div>
            </div>
            <x-ui.btn variant="primary" size="sm" type="button" @click="saveViewOpen = true">Save view</x-ui.btn>
        </div>
    </div>

    @if(session('success'))
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    @endif

    <div class="rounded-2xl border border-[#e6eef9] bg-white p-4 space-y-4" :class="{ 'opacity-60 pointer-events-none': loading }">
        <div class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-[11px] font-semibold text-[#64748b] mb-1">Dataset</label>
                <select x-model="dataset" @change="refresh()" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px] min-w-[180px]">
                    <template x-for="d in datasets" :key="d.value">
                        <option :value="d.value" x-text="d.label" :selected="d.value === dataset"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-[#64748b] mb-1">Date range</label>
                <select x-model="config.date_preset" @change="onDatePresetChange()" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px] min-w-[150px]">
                    <template x-for="p in datePresets" :key="p.value">
                        <option :value="p.value" x-text="p.label"></option>
                    </template>
                </select>
            </div>
            <div x-show="config.date_preset === 'custom' || !config.date_preset">
                <label class="block text-[11px] font-semibold text-[#64748b] mb-1">From</label>
                <input type="date" x-model="config.date_from" @change="refresh()" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
            </div>
            <div x-show="config.date_preset === 'custom' || !config.date_preset">
                <label class="block text-[11px] font-semibold text-[#64748b] mb-1">To</label>
                <input type="date" x-model="config.date_to" @change="refresh()" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-[#64748b] mb-1">Status filter</label>
                <select x-show="statusOptions.length" x-model="config.status" @change="refresh()" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px] min-w-[150px]">
                    <option value="">All statuses</option>
                    <template x-for="s in statusOptions" :key="s.value">
                        <option :value="s.value" x-text="s.label"></option>
                    </template>
                </select>
                <input x-show="!statusOptions.length" type="text" x-model="config.status" @change="refresh()" placeholder="e.g. Completed" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
            </div>
            <div x-show="hasFilter('county')">
                <label class="block text-[11px] font-semibold text-[#64748b] mb-1">County</label>
                <input type="text" x-model="config.county" @change="refresh()" placeholder="e.g. Wayne" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
            </div>
            <div x-show="hasFilter('program')">
                <label class="block text-[11px] font-semibold text-[#64748b] mb-1">Program</label>
                <input type="text" x-model="config.program" @change="refresh()" placeholder="e.g. DHS" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
            </div>
            <div x-show="hasFilter('client_id')">
                <label class="block text-[11px] font-semibold text-[#64748b] mb-1">Client</label>
                <select x-model="config.client_id" @change="refresh()" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px] min-w-[160px]">
                    <option value="">All clients</option>
                    <template x-for="c in clients" :key="c.id">
                        <option :value="c.id" x-text="c.name"></option>
                    </template>
                </select>
            </div>
            <div x-show="hasFilter('employee_id')">
                <label class="block text-[11px] font-semibold text-[#64748b] mb-1">Caregiver</label>
                <select x-model="config.employee_id" @change="refresh()" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px] min-w-[160px]">
                    <option value="">All caregivers</option>
                    <template x-for="cg in caregivers" :key="cg.id">
                        <option :value="cg.id" x-text="cg.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-[#64748b] mb-1">Group by</label>
                <select x-model="config.group_by" @change="refresh()" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
                    <option value="">No grouping</option>
                    <template x-for="g in groupByOptions" :key="g.value">
                        <option :value="g.value" x-text="g.label"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-[#64748b] mb-1">Aggregate</label>
                <select x-model="config.aggregate" @change="refresh()" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
                    <template x-for="a in aggregateOptions" :key="a.value">
                        <option :value="a.value" x-text="a.label"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-[#64748b] mb-1">Chart</label>
                <select x-model="config.chart_type" @change="refresh()" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
                    <template x-for="(label, key) in chartTypes" :key="key">
                        <option :value="key" x-text="label"></option>
                    </template>
                </select>
            </div>
            <div x-show="loading" class="text-[12px] text-[#64748b] self-center pb-2">Updating…</div>
        </div>

        <div x-show="savedViews.length" class="flex flex-wrap gap-2 border-t border-[#eef2f9] pt-3">
            <span class="text-[11px] font-semibold text-[#64748b] self-center">Saved views:</span>
            <template x-for="v in savedViews" :key="v.id">
                <span class="inline-flex items-center gap-1 rounded-full border border-[#e2e8f0] pl-3 pr-1 py-0.5">
                    <button type="button" @click="loadView(v)"
                            class="text-[11px] font-semibold text-[#475569] hover:text-[#2563eb]">
                        <span x-text="v.name"></span>
                        <span x-show="v.schedule_frequency" class="text-[#94a3b8] font-normal"
                              x-text="v.schedule_frequency ? ' · ' + v.schedule_frequency : ''"></span>
                    </button>
                    <button type="button" @click="deleteView(v)"
                            class="rounded-full px-1.5 py-0.5 text-[11px] font-bold text-[#94a3b8] hover:text-[#dc2626] hover:bg-[#fef2f2]"
                            title="Delete view">&times;</button>
                </span>
            </template>
        </div>
    </div>

    <p x-show="truncated" class="text-[12px] text-[#b45309] bg-[#fffbeb] border border-[#fde68a] rounded-xl px-3 py-2">
        Showing first 500 rows. Group or add filters for full totals — grouped views use up to 5,000 matching records.
    </p>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-[#e6eef9] bg-white p-4 flex flex-col min-h-0">
            <h3 class="text-[14px] font-bold text-[#0f172a] mb-3 shrink-0">
                Results
                <span class="text-[#94a3b8] font-normal text-[12px]" x-text="`(${rows.length} rows)`"></span>
            </h3>
            <div class="overflow-x-auto overflow-y-auto max-h-[min(420px,50vh)] -mx-1 px-1">
                <table class="w-full text-left text-[13px]">
                    <thead class="sticky top-0 z-10 bg-white">
                        <tr class="border-b border-[#e2e8f0] text-[11px] uppercase text-[#64748b]">
                            <template x-for="col in columns" :key="col.label">
                                <th class="py-2 pr-3 font-semibold bg-white" x-text="col.label"></th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, ri) in rows" :key="ri">
                            <tr class="border-b border-[#f1f5f9]">
                                <template x-for="col in columns" :key="col.label">
                                    <td class="py-2 pr-3" x-text="row[col.label] ?? '—'"></td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p x-show="rows.length === 0 && !loading" class="text-[13px] text-[#64748b] py-6 text-center">No rows match your filters.</p>
            </div>
        </div>

        <div class="rounded-2xl border border-[#e6eef9] bg-white p-4" x-show="config.chart_type !== 'table'">
            <h3 class="text-[14px] font-bold text-[#0f172a] mb-3">Chart</h3>
            <div id="exploration-chart" class="min-h-[280px]"></div>
        </div>
    </div>

    <div x-show="saveViewOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-[#0f172a]/40" @click="saveViewOpen = false"></div>
        <div class="relative w-full max-w-sm rounded-2xl bg-white border border-[#e2e8f0] p-5 space-y-3">
            <h3 class="font-bold text-[#0f172a]">Save this view</h3>
            <input type="text" x-model="viewName" placeholder="View name" class="w-full rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
            <div>
                <label class="block text-[11px] font-semibold text-[#64748b] mb-1">Email schedule (optional)</label>
                <select x-model="viewSchedule" class="w-full rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
                    <option value="">Don't email — save only</option>
                    <option value="daily">Email me daily</option>
                    <option value="weekly">Email me weekly</option>
                </select>
                <p class="text-[11px] text-[#94a3b8] mt-1">Scheduled views are emailed with a CSV of the current filters.</p>
            </div>
            <div class="flex justify-end gap-2">
                <x-ui.btn variant="outline" size="sm" type="button" @click="saveViewOpen = false">Cancel</x-ui.btn>
                <x-ui.btn variant="primary" size="sm" type="button" @click="saveView()">Save</x-ui.btn>
            </div>
        </div>
    </div>
</div>

<script>
function dataExplorationPage(initial) {
    return {
        dataset: initial.dataset,
        config: {
            date_preset: 'custom',
            ...initial.config,
        },
        columns: initial.columns,
        rows: initial.rows,
        chart: initial.chart,
        truncated: initial.truncated || false,
        totalMatched: initial.totalMatched || 0,
        datasets: initial.datasets,
        groupByOptions: initial.groupByOptions,
        aggregateOptions: initial.aggregateOptions,
        filterFields: initial.filterFields || [],
        statusOptions: initial.statusOptions || [],
        datePresets: initial.datePresets || [],
        clients: initial.clients || [],
        caregivers: initial.caregivers || [],
        chartTypes: initial.chartTypes,
        savedViews: initial.savedViews,
        csrfToken: initial.csrfToken,
        chartInstance: null,
        saveViewOpen: false,
        viewName: '',
        viewSchedule: '',
        loading: false,

        hasFilter(field) {
            return Array.isArray(this.filterFields) && this.filterFields.includes(field);
        },

        onDatePresetChange() {
            if (this.config.date_preset && this.config.date_preset !== 'custom') {
                this.config.date_from = '';
                this.config.date_to = '';
            }
            this.refresh();
        },

        exportUrl(format = 'csv') {
            const params = new URLSearchParams({ dataset: this.dataset, format, ...this.config });
            Object.keys(this.config || {}).forEach((key) => {
                if (this.config[key] === null || this.config[key] === undefined || this.config[key] === '') {
                    params.delete(key);
                }
            });
            return '{{ route('data-exploration.export') }}?' + params.toString();
        },

        async refresh() {
            this.loading = true;
            try {
                const res = await fetch('{{ route('data-exploration.query') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({ dataset: this.dataset, ...this.config }),
                });
                const data = await res.json();
                this.columns = data.columns;
                this.rows = data.rows;
                this.chart = data.chart;
                this.truncated = !!data.truncated;
                this.totalMatched = data.total_matched || 0;
                if (data.group_by_options) this.groupByOptions = data.group_by_options;
                if (data.aggregate_options) this.aggregateOptions = data.aggregate_options;
                if (data.filter_fields) this.filterFields = data.filter_fields;
                if (data.status_options) this.statusOptions = data.status_options;
                if (data.date_presets) this.datePresets = data.date_presets;

                const groupValues = (this.groupByOptions || []).map((g) => g.value);
                if (this.config.group_by && !groupValues.includes(this.config.group_by)) {
                    this.config.group_by = '';
                }
                const aggregateValues = (this.aggregateOptions || []).map((a) => a.value);
                if (!aggregateValues.includes(this.config.aggregate)) {
                    this.config.aggregate = aggregateValues[0] || 'count';
                }

                this.renderChart();
            } finally {
                this.loading = false;
            }
        },

        loadView(view) {
            this.dataset = view.dataset;
            this.config = { date_preset: 'custom', ...this.config, ...view.config };
            this.refresh();
        },

        async saveView() {
            await fetch('{{ route('data-exploration.save-view') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    name: this.viewName,
                    dataset: this.dataset,
                    config: this.config,
                    schedule_frequency: this.viewSchedule || null,
                }),
            });
            window.location.reload();
        },

        async deleteView(view) {
            if (!confirm('Delete saved view "' + view.name + '"?')) return;
            const res = await fetch('{{ url('/data-exploration/views') }}/' + view.id, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });
            if (res.ok) {
                this.savedViews = this.savedViews.filter((v) => v.id !== view.id);
            }
        },

        renderChart() {
            if (!window.ApexCharts || this.config.chart_type === 'table') return;
            const el = document.querySelector('#exploration-chart');
            if (!el) return;
            if (this.chartInstance) this.chartInstance.destroy();
            const type = this.config.chart_type === 'line' ? 'line' : (this.config.chart_type === 'pie' ? 'pie' : 'bar');
            this.chartInstance = new ApexCharts(el, {
                chart: { type, height: 280, toolbar: { show: false } },
                series: type === 'pie'
                    ? this.chart.values
                    : [{ name: 'Total', data: this.chart.values }],
                labels: this.chart.labels,
                xaxis: type !== 'pie' ? { categories: this.chart.labels } : undefined,
                colors: ['#2563eb'],
                dataLabels: { enabled: false },
            });
            this.chartInstance.render();
        },
    };
}
</script>
@endsection
