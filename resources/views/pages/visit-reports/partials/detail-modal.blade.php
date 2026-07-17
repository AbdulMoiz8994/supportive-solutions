<div x-show="detailOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="detailOpen = false">
    <div class="absolute inset-0 bg-[#0f172a]/40" @click="detailOpen = false"></div>
    <div class="relative w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl border border-[#e2e8f0] bg-white shadow-xl">
        <div class="sticky top-0 flex items-center justify-between border-b border-[#e2e8f0] bg-white px-5 py-4">
            <h2 class="text-[16px] font-bold text-[#0f172a]">Visit detail</h2>
            <button type="button" @click="detailOpen = false" class="text-[#94a3b8] hover:text-[#0f172a] text-xl">&times;</button>
        </div>

        <div class="p-5 space-y-4" x-show="loading">
            <p class="text-[13px] text-[#64748b]">Loading…</p>
        </div>

        <template x-if="detail && !loading">
            <div class="p-5 space-y-5">
                <div x-show="toast" class="rounded-lg border border-[#d1fadf] bg-[#ecfdf3] px-3 py-2 text-[12px] font-semibold text-[#067647]" x-text="toast"></div>

                <div class="grid grid-cols-2 gap-3 text-[13px]">
                    <div><span class="text-[#94a3b8]">Caregiver</span><div class="font-semibold" x-text="detail.caregiver"></div></div>
                    <div><span class="text-[#94a3b8]">Client</span><div class="font-semibold" x-text="detail.client"></div></div>
                    <div><span class="text-[#94a3b8]">Date</span><div x-text="detail.date"></div></div>
                    <div><span class="text-[#94a3b8]">Duration</span><div x-text="detail.duration"></div></div>
                    <div><span class="text-[#94a3b8]">Clock in</span><div x-text="detail.clock_in"></div></div>
                    <div><span class="text-[#94a3b8]">Clock out</span><div x-text="detail.clock_out"></div></div>
                    <div><span class="text-[#94a3b8]">Units</span><div x-text="detail.units"></div></div>
                    <div><span class="text-[#94a3b8]">Remaining auth units</span><div x-text="detail.remaining_auth_units ?? '—'"></div></div>
                </div>

                <div class="rounded-xl border border-[#e2e8f0] bg-[#f8fbff] p-4">
                    <div class="text-[12px] font-semibold text-[#64748b] mb-2">Location</div>
                    <p class="text-[13px] text-[#334155]" x-text="detail.address || 'No address on file'"></p>
                    <p class="text-[12px] mt-2" x-show="detail.clock_in_coords">
                        Clock-in GPS: <span x-text="detail.clock_in_coords ? `${detail.clock_in_coords.lat}, ${detail.clock_in_coords.lng}` : ''"></span>
                        · Match: <span class="font-semibold" x-text="detail.location_match"></span>
                    </p>
                    <div class="mt-3 overflow-hidden rounded-lg border border-[#e2e8f0] bg-white"
                         x-show="detail.map_points && detail.map_points.length"
                         x-html="mapEmbedHtml(detail)"></div>
                    <ul class="mt-2 space-y-1" x-show="detail.map_points && detail.map_points.length">
                        <template x-for="(point, idx) in (detail.map_points || [])" :key="idx">
                            <li class="text-[11px] text-[#64748b]">
                                <span class="font-semibold text-[#334155]" x-text="point.label"></span>:
                                <span x-text="`${point.lat}, ${point.lng}`"></span>
                            </li>
                        </template>
                    </ul>
                    <div x-show="detail.can_approve_location" class="mt-3 space-y-2 border-t border-[#e2e8f0] pt-3">
                        <p class="text-[12px] text-[#b45309]">Location mismatch flagged. Approve with a reason to keep original GPS and clear the review flag.</p>
                        <textarea x-model="locationOverride.reason" rows="2" placeholder="Why is this location acceptable? (e.g. client was at adult day program)"
                                  class="w-full rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]"></textarea>
                        <x-ui.btn variant="primary" size="sm" type="button" @click="approveLocation()">Approve location</x-ui.btn>
                    </div>
                </div>

                <div x-show="detail.location_overrides?.length">
                    <div class="text-[12px] font-semibold text-[#64748b] mb-2">Location override audit trail</div>
                    <template x-for="(o, i) in detail.location_overrides" :key="i">
                        <div class="text-[12px] border border-[#e2e8f0] rounded-lg p-3 mb-2">
                            <div>Approved by <span class="font-semibold" x-text="o.by_user_name"></span>
                                <span class="text-[#94a3b8]" x-text="o.approved_at ? (' · ' + o.approved_at) : ''"></span>
                            </div>
                            <div class="text-[#64748b] mt-1">Reason: <span x-text="o.reason"></span></div>
                            <div class="text-[#64748b] mt-1" x-show="o.original_clock_in">
                                Original clock-in GPS:
                                <span x-text="o.original_clock_in ? `${o.original_clock_in.lat}, ${o.original_clock_in.lng}` : ''"></span>
                            </div>
                            <div class="text-[#64748b]" x-show="o.original_clock_out">
                                Original clock-out GPS:
                                <span x-text="o.original_clock_out ? `${o.original_clock_out.lat}, ${o.original_clock_out.lng}` : ''"></span>
                            </div>
                            <div class="mt-1 text-[#067647] font-semibold" x-show="o.approved">GPS preserved — original pins unchanged</div>
                        </div>
                    </template>
                </div>

                <div x-show="detail.care_tasks && detail.care_tasks.length">
                    <div class="text-[12px] font-semibold text-[#64748b] mb-2">Care tasks done</div>
                    <ul class="space-y-1.5">
                        <template x-for="(task, idx) in (detail.care_tasks || [])" :key="idx">
                            <li class="flex items-center gap-2 text-[13px] text-[#334155]">
                                <span class="inline-flex h-4 w-4 items-center justify-center rounded border text-[10px]"
                                      :class="task.completed ? 'bg-[#ecfdf3] border-[#a7f3d0] text-[#067647]' : 'border-[#e2e8f0] text-[#94a3b8]'"
                                      x-text="task.completed ? '✓' : ''"></span>
                                <span x-text="task.label || task"></span>
                            </li>
                        </template>
                    </ul>
                </div>

                <div x-show="detail.visit_notes">
                    <div class="text-[12px] font-semibold text-[#64748b] mb-1">Caregiver note</div>
                    <p class="text-[13px] text-[#334155]" x-text="detail.visit_notes"></p>
                </div>

                <div x-show="detail.time_corrections?.length">
                    <div class="text-[12px] font-semibold text-[#64748b] mb-2">Time correction audit trail</div>
                    <div class="text-[12px] rounded-lg border border-[#e2e8f0] bg-[#f8fbff] px-3 py-2 mb-2"
                         x-show="detail.original_evv">
                        <span class="font-semibold text-[#334155]">Original EVV (immutable):</span>
                        <span class="text-[#64748b]" x-show="detail.original_evv?.actual_clock_in">
                            In <span x-text="detail.original_evv?.actual_clock_in"></span>
                        </span>
                        <span class="text-[#64748b]" x-show="detail.original_evv?.actual_clock_out">
                            · Out <span x-text="detail.original_evv?.actual_clock_out"></span>
                        </span>
                    </div>
                    <template x-for="(c, i) in detail.time_corrections" :key="i">
                        <div class="text-[12px] border border-[#e2e8f0] rounded-lg p-3 mb-2">
                            <div><span class="font-semibold" x-text="c.field"></span> — proposed by <span x-text="c.by_user_name"></span></div>
                            <div class="text-[#64748b] mt-1">Original: <span x-text="c.original"></span> → Proposed: <span x-text="c.proposed"></span></div>
                            <div class="text-[#64748b]">Reason: <span x-text="c.reason"></span></div>
                            <div class="mt-1 font-semibold" :class="c.approved ? 'text-[#067647]' : 'text-[#b45309]'"
                                 x-text="c.approved ? 'Approved' : 'Pending approval'"></div>
                        </div>
                    </template>
                </div>

                <div x-show="detail.status === 'needs_review'" class="border-t border-[#e2e8f0] pt-4 space-y-3">
                    <div class="text-[13px] font-bold text-[#0f172a]">Fix / Approve</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <select x-model="correction.field" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
                            <option value="actual_clock_out">Clock-out time</option>
                            <option value="actual_clock_in">Clock-in time</option>
                        </select>
                        <input type="datetime-local" x-model="correction.proposed_time" class="rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]">
                    </div>
                    <textarea x-model="correction.reason" rows="2" placeholder="Why is this correction needed?" class="w-full rounded-lg border border-[#e2e8f0] px-3 py-2 text-[13px]"></textarea>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.btn variant="outline" size="sm" type="button" @click="proposeCorrection()">Submit correction</x-ui.btn>
                        <x-ui.btn variant="primary" size="sm" type="button" @click="approveCorrection()">Approve pending</x-ui.btn>
                        <x-ui.btn variant="outline" size="sm" type="button" @click="markMissed()">Mark missed</x-ui.btn>
                    </div>
                </div>

                <div x-show="detail.billable" class="rounded-lg bg-[#ecfdf3] border border-[#a7f3d0] px-3 py-2 text-[12px] font-semibold text-[#067647]">
                    Billable — ready for billing pipeline
                </div>
            </div>
        </template>
    </div>
</div>
