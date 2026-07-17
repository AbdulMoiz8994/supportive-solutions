<div class="space-y-6" x-data="{ prompt: @js($data['prompt'] ?? ''), loading: false }">
    <form method="POST" action="{{ route('reports.custom.run') }}" @submit="loading = true">
        @csrf
        <div class="rounded-xl border border-[#e2e8f0] bg-white p-4 mb-4">
            <h3 class="text-[14px] font-semibold text-[#0f172a]">Ask in plain language</h3>
            <p class="text-[12px] text-[#94a3b8] mb-3">Describe the report — filters and columns are inferred automatically</p>
            <textarea name="prompt" x-model="prompt" rows="3"
                      class="w-full bg-[#f1f5f9] border border-[#e2e8f0] rounded-lg px-3.5 py-3 text-[13.5px] text-[#334155] focus:outline-none focus:ring-2 focus:ring-[#bfdbfe]"></textarea>
            <button type="submit" :disabled="loading"
                    class="mt-3 inline-flex items-center px-4 py-2 text-[12px] font-semibold text-white bg-[#2563eb] rounded-xl hover:bg-[#1d4ed8] transition disabled:opacity-60">
                <span x-text="loading ? 'Generating…' : 'Generate report'"></span>
            </button>
        </div>
    </form>

    <form method="POST" action="{{ route('reports.custom.save') }}">
        @csrf
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="rounded-xl border border-[#e2e8f0] bg-white p-4">
                <h3 class="text-[14px] font-semibold text-[#0f172a]">…or build by fields</h3>
                <p class="text-[12px] text-[#94a3b8] mb-4">Pick source, columns, filters, group-by</p>
                <div class="space-y-3 text-[12.5px]">
                    <div>
                        <label class="text-[#94a3b8] text-[11px] uppercase">Report name</label>
                        <input type="text" name="name" required placeholder="DHS hours drop by ASW"
                               class="mt-1 w-full px-3 py-2 border border-[#e2e8f0] rounded-lg text-[13px]">
                    </div>
                    <div>
                        <label class="text-[#94a3b8] text-[11px] uppercase">Source</label>
                        <select name="source" class="mt-1 w-full px-3 py-2 border border-[#e2e8f0] rounded-lg text-[13px]">
                            @foreach(['clients' => 'Clients + Compliance', 'caregivers' => 'Caregivers', 'billing' => 'Billing', 'compliance' => 'Compliance'] as $val => $label)
                                <option value="{{ $val }}" @selected(($data['fields']['source'] ?? 'clients') === $val || str_contains(strtolower($data['fields']['source'] ?? ''), $val))>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-[#94a3b8] text-[11px] uppercase">Group by</label>
                        <input type="text" name="group_by" value="{{ $data['fields']['group_by'] ?? 'ASW' }}"
                               class="mt-1 w-full px-3 py-2 border border-[#e2e8f0] rounded-lg text-[13px]">
                    </div>
                    <input type="hidden" name="prompt" value="{{ $data['prompt'] ?? '' }}">
                    @foreach($data['fields']['columns'] ?? ['Client', 'Program', 'Hours Δ', 'ASW'] as $col)
                        <input type="hidden" name="columns[]" value="{{ $col }}">
                    @endforeach
                </div>
                <button type="submit" class="mt-4 inline-flex items-center px-4 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc]">
                    Save to library
                </button>
            </div>
            <div class="rounded-xl border border-[#e2e8f0] bg-white p-4">
                <h3 class="text-[14px] font-semibold text-[#0f172a]">Preview</h3>
                <p class="text-[12px] text-[#94a3b8] mb-3">Live as you build</p>
                @php $previewCols = array_keys($data['preview'][0] ?? ['Client' => '', 'Program' => '', 'County' => '']); @endphp
                <table class="w-full text-left text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase text-[#94a3b8] border-b border-[#e2e8f0]">
                            @foreach($previewCols as $col)
                                <th class="py-2">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['preview'] as $row)
                            <tr class="border-b border-[#f1f5f9]">
                                @foreach($previewCols as $col)
                                    <td class="py-2 font-semibold">{{ $row[$col] ?? '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    @if(!empty($data['sections']))
        @include('pages.reports.types.standard-report', ['data' => $data, 'cols' => 4])
    @endif
</div>
