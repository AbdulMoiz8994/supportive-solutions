@extends('layouts.app')

@section('content')
<div class="space-y-6"
     x-data="workflowQueuesPage(@js([
         'kpis' => $kpis,
         'subtitle' => $subtitle,
         'sectionApprovalsLabel' => $sectionApprovalsLabel,
         'sectionHumanLabel' => $sectionHumanLabel,
         'sectionExceptionsLabel' => $sectionExceptionsLabel,
         'approvalsMeta' => $approvalsMeta,
         'approvalsUrl' => route('workflow-queues.approvals'),
         'sort' => $sort ?? 'sla',
         'filter' => $filter,
         'sortOptions' => $sortOptions ?? [],
         'filterOptions' => $filterOptions ?? [],
         'csrfToken' => $csrfToken,
     ]))"
     @submit.prevent="submitAction">

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-[28px] font-extrabold text-[#0f172a] tracking-tight leading-tight">Workflow Queues</h1>
            <p class="text-[13px] text-[#64748b] mt-1.5" x-text="subtitle">{{ $subtitle }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative" x-on:click.outside="sortMenuOpen = false">
                <button type="button"
                        x-on:click="sortMenuOpen = !sortMenuOpen; filterMenuOpen = false"
                        class="inline-flex items-center justify-center gap-1.5 text-sm font-semibold rounded-[9px] px-3.5 py-2 bg-white text-[#475569] border border-[#d8e2f0] hover:border-[#94a3b8] hover:text-[#1e293b] transition-all duration-150 whitespace-nowrap"
                        x-bind:aria-expanded="sortMenuOpen.toString()">
                    <span x-text="'Sort: ' + sortLabel()">SLA · urgent first</span>
                    <span class="text-[#94a3b8]">▾</span>
                </button>
                <div x-show="sortMenuOpen" x-cloak x-transition
                     class="absolute right-0 mt-1.5 w-52 bg-white border border-[#e2e8f0] rounded-xl shadow-lg z-20 py-1">
                    <template x-for="option in sortOptions" :key="option.value">
                        <button type="button"
                                class="w-full text-left px-3.5 py-2 text-[12.5px] font-medium transition-colors"
                                :class="sort === option.value ? 'bg-[#eff6ff] text-[#1d4ed8]' : 'text-[#334155] hover:bg-[#f8fafc]'"
                                x-bind:data-sort="option.value"
                                x-on:click.stop="applySort($event.currentTarget.dataset.sort)"
                                x-text="option.label"></button>
                    </template>
                </div>
            </div>

            <div class="relative" x-on:click.outside="filterMenuOpen = false">
                <button type="button"
                        x-on:click="filterMenuOpen = !filterMenuOpen; sortMenuOpen = false"
                        class="inline-flex items-center justify-center gap-1.5 text-sm font-semibold rounded-[9px] px-3.5 py-2 bg-white text-[#475569] border border-[#d8e2f0] hover:border-[#94a3b8] hover:text-[#1e293b] transition-all duration-150 whitespace-nowrap"
                        :class="filter ? 'border-[#93c5fd] text-[#1d4ed8] bg-[#eff6ff]' : ''"
                        x-bind:aria-expanded="filterMenuOpen.toString()">
                    <span x-text="filterLabel()">Filter</span>
                    <span class="text-[#94a3b8]">▾</span>
                </button>
                <div x-show="filterMenuOpen" x-cloak x-transition
                     class="absolute right-0 mt-1.5 w-52 bg-white border border-[#e2e8f0] rounded-xl shadow-lg z-20 py-1 max-h-72 overflow-y-auto">
                    <template x-for="option in filterOptions" :key="option.value || 'all'">
                        <button type="button"
                                class="w-full text-left px-3.5 py-2 text-[12.5px] font-medium transition-colors"
                                :class="(filter || '') === (option.value || '') ? 'bg-[#eff6ff] text-[#1d4ed8]' : 'text-[#334155] hover:bg-[#f8fafc]'"
                                x-bind:data-filter="option.value || ''"
                                x-on:click.stop="applyFilter($event.currentTarget.dataset.filter)"
                                x-text="option.label"></button>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
        <template x-for="kpi in kpis" :key="kpi.label">
            <div class="rounded-xl border border-[#e2e8f0] bg-white px-3.5 py-3">
                <div class="text-[11.5px] text-[#64748b] mb-1" x-text="kpi.label"></div>
                <div class="text-[20px] font-bold leading-tight"
                     :class="{
                        'text-[#047857]': kpi.tone === 'ok',
                        'text-[#b45309]': kpi.tone === 'alert',
                        'text-[#b91c1c]': kpi.tone === 'danger',
                        'text-[#0f172a]': !kpi.tone || kpi.tone === 'default',
                     }"
                     x-text="kpi.value"></div>
                <div class="text-[11px] text-[#94a3b8] mt-1" x-show="kpi.sub" x-text="kpi.sub"></div>
            </div>
        </template>
    </div>

    <div class="space-y-3">
        <div x-show="toast" x-cloak x-transition
             class="rounded-xl border border-[#d1fadf] bg-[#ecfdf3] px-4 py-2.5 text-sm font-semibold text-[#067647]"
             x-text="toast"></div>

        @if(session('success'))
            <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if(session('error'))
            <x-ui.alert variant="warning">{{ session('error') }}</x-ui.alert>
        @endif
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[1.55fr_1fr] gap-5 items-start">
        <section>
            <div class="flex items-center gap-2 mb-3">
                <span class="w-[30px] h-[30px] rounded-lg bg-[#dbeafe] flex items-center justify-center text-[15px]">✓</span>
                <h2 class="text-[15px] font-bold text-[#0f172a]">Owner Approval Queue</h2>
                <span class="ml-auto text-[12px] text-[#94a3b8]" x-text="sectionApprovalsLabel">{{ $sectionApprovalsLabel }}</span>
            </div>

            <div id="approval-queue-cards" x-bind:class="reloading && 'opacity-50 pointer-events-none'">
                @forelse($approvals as $card)
                    @include('pages.workflow-queues.partials.approval-card', ['card' => $card])
                @empty
                    <div class="rounded-xl border border-[#e2e8f0] bg-white p-8 text-center text-[#64748b] text-[13px]" data-queue-empty="approvals">
                        {{ !empty($filter) ? 'No pending approvals match this filter.' : "No pending approvals — you're caught up." }}
                    </div>
                @endforelse
            </div>

            <div class="mt-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
                 x-show="approvalsMeta.total > 0"
                 x-cloak>
                <p class="text-[12px] text-[#94a3b8]"
                   x-text="`Showing ${approvalsMeta.loaded} of ${approvalsMeta.total}`"></p>
                <div x-show="approvalsMeta.hasMore">
                    <x-ui.btn
                        variant="outline"
                        type="button"
                        size="sm"
                        x-on:click="loadMore"
                        x-bind:disabled="loadingMore"
                        x-text="loadingMore ? 'Loading…' : 'Load more'"
                    />
                </div>
            </div>
        </section>

        <div class="space-y-5">
            <section>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-[30px] h-[30px] rounded-lg bg-[#fef3c7] flex items-center justify-center text-[15px]">📋</span>
                    <h2 class="text-[15px] font-bold text-[#0f172a]">Physical / Human Tasks</h2>
                    <span class="ml-auto text-[12px] text-[#94a3b8]" x-text="sectionHumanLabel">{{ $sectionHumanLabel }}</span>
                </div>
                <div class="rounded-[11px] border border-[#e2e8f0] bg-white overflow-hidden">
                    @forelse($humanTasks as $task)
                        <div class="flex gap-3 px-4 py-3 border-b border-[#f1f5f9] last:border-b-0 items-start" data-queue-slug="{{ $task['slug'] }}">
                            <form action="{{ route('workflow-queues.action', $task['slug']) }}" method="POST" class="mt-0.5 queue-action-form" data-queue-slug="{{ $task['slug'] }}">
                                @csrf
                                <input type="hidden" name="queue_action" value="complete">
                                <button type="submit" class="w-[18px] h-[18px] rounded-[5px] border-[1.5px] border-[#cbd5e1] hover:border-[#2563eb] hover:bg-[#eff6ff]" title="Mark complete"></button>
                            </form>
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-semibold text-[#0f172a]">{{ $task['title'] }}</p>
                                <p class="text-[11.5px] text-[#64748b] mt-0.5">{{ $task['description'] ?? '' }}</p>
                                <div class="flex flex-wrap gap-2.5 mt-1.5 text-[11px] text-[#94a3b8]">
                                    <span>👤 {{ $task['assignee'] }}</span>
                                    <span class="{{ ($task['due_tone'] ?? '') === 'urgent' ? 'text-[#b45309] font-semibold' : '' }}">{{ $task['due'] }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-[13px] text-[#64748b]" data-queue-empty="human">No human tasks pending.</div>
                    @endforelse
                </div>
            </section>

            <section>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-[30px] h-[30px] rounded-lg bg-[#ede9fe] flex items-center justify-center text-[15px]">🤖</span>
                    <h2 class="text-[15px] font-bold text-[#0f172a]">Exceptions</h2>
                    <span class="ml-auto text-[12px] text-[#94a3b8]" x-text="sectionExceptionsLabel">{{ $sectionExceptionsLabel }}</span>
                </div>
                <div class="rounded-[11px] border border-[#e2e8f0] bg-white overflow-hidden">
                    <div class="mx-4 my-3 rounded-lg border border-[#a7f3d0] bg-[#ecfdf5] px-3 py-2.5 text-[12px] text-[#065f46] flex gap-2 items-center">
                        ✅ Agent miss-rate this week <strong>{{ $kpis[3]['value'] ?? '1.2%' }}</strong> — under the {{ $missRateThreshold ?? 2 }}% alert threshold. Items below confidence are routed here for review.
                    </div>
                    @forelse($exceptions as $exception)
                        <div class="flex gap-3 px-4 py-3 border-b border-[#f1f5f9] last:border-b-0 items-start" data-queue-slug="{{ $exception['slug'] }}">
                            <span class="text-[15px]">{{ $exception['icon'] ?? '⚠️' }}</span>
                            <div>
                                <p class="text-[12.5px] font-semibold text-[#0f172a]">{{ $exception['title'] }}</p>
                                <p class="text-[11.5px] text-[#64748b] mt-0.5">{{ $exception['description'] ?? '' }}</p>
                                @if(!empty($exception['link_label']))
                                    <form action="{{ route('workflow-queues.action', $exception['slug']) }}" method="POST" class="mt-1.5 inline queue-action-form" data-queue-slug="{{ $exception['slug'] }}">
                                        @csrf
                                        <input type="hidden" name="queue_action" value="approve">
                                        <button type="submit" class="text-[11px] text-[#2563eb] font-semibold hover:underline">{{ $exception['link_label'] }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-4 text-[13px] text-[#64748b]" data-queue-empty="exceptions">No exceptions flagged.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</div>

<script>
function workflowQueuesPage(initial) {
    return {
        kpis: initial.kpis || [],
        subtitle: initial.subtitle || '',
        sectionApprovalsLabel: initial.sectionApprovalsLabel || '',
        sectionHumanLabel: initial.sectionHumanLabel || '',
        sectionExceptionsLabel: initial.sectionExceptionsLabel || '',
        approvalsMeta: Object.assign({
            total: 0,
            offset: 0,
            limit: 12,
            loaded: 0,
            hasMore: false,
        }, initial.approvalsMeta || {}),
        approvalsUrl: initial.approvalsUrl || '',
        sort: initial.sort || 'sla',
        filter: initial.filter || '',
        sortOptions: initial.sortOptions || [],
        filterOptions: initial.filterOptions || [],
        sortMenuOpen: false,
        filterMenuOpen: false,
        csrfToken: initial.csrfToken,
        toast: null,
        submitting: false,
        loadingMore: false,
        reloading: false,
        sortLabel() {
            const match = (this.sortOptions || []).find((option) => option.value === this.sort);
            return match?.label || 'SLA · urgent first';
        },
        filterLabel() {
            if (!this.filter) {
                return 'Filter';
            }
            const match = (this.filterOptions || []).find((option) => (option.value || '') === this.filter);
            return match ? `Filter: ${match.label}` : 'Filter';
        },
        visibleApprovalCount() {
            return document.querySelectorAll('#approval-queue-cards article[data-queue-slug]').length;
        },
        syncApprovalsMeta(total = null) {
            const loaded = this.visibleApprovalCount();
            const nextTotal = typeof total === 'number' ? total : this.approvalsMeta.total;
            this.approvalsMeta = Object.assign({}, this.approvalsMeta, {
                total: nextTotal,
                loaded,
                hasMore: loaded < nextTotal,
            });
        },
        syncUrl() {
            const url = new URL(window.location.href);
            if (this.sort && this.sort !== 'sla') {
                url.searchParams.set('sort', this.sort);
            } else {
                url.searchParams.delete('sort');
            }
            if (this.filter) {
                url.searchParams.set('filter', this.filter);
            } else {
                url.searchParams.delete('filter');
            }
            window.history.replaceState({}, '', url);
        },
        approvalsQuery(offset = 0) {
            const url = new URL(this.approvalsUrl, window.location.origin);
            url.searchParams.set('offset', String(offset));
            url.searchParams.set('limit', String(this.approvalsMeta.limit || 12));
            url.searchParams.set('sort', this.sort || 'sla');
            if (this.filter) {
                url.searchParams.set('filter', this.filter);
            } else {
                url.searchParams.delete('filter');
            }
            return url;
        },
        applySort(value) {
            this.sortMenuOpen = false;
            this.filterMenuOpen = false;
            const next = value || 'sla';
            if (this.sort === next) {
                return this.reloadApprovals();
            }
            this.sort = next;
            this.syncUrl();
            return this.reloadApprovals();
        },
        applyFilter(value) {
            this.filterMenuOpen = false;
            this.sortMenuOpen = false;
            const next = value || '';
            if (this.filter === next) {
                return this.reloadApprovals();
            }
            this.filter = next;
            this.syncUrl();
            return this.reloadApprovals();
        },
        async reloadApprovals() {
            if (!this.approvalsUrl || this.reloading) {
                return;
            }

            this.reloading = true;
            this.toast = null;

            try {
                const response = await fetch(this.approvalsQuery(0).toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok || !data.ok) {
                    throw new Error(data.message || `Could not refresh queue (${response.status}).`);
                }

                const container = document.getElementById('approval-queue-cards');
                if (container) {
                    container.innerHTML = data.html || '';
                }

                if (data.sort) {
                    this.sort = data.sort;
                }
                if (Object.prototype.hasOwnProperty.call(data, 'filter')) {
                    this.filter = data.filter || '';
                }

                if (data.approvalsMeta) {
                    this.approvalsMeta = Object.assign({}, this.approvalsMeta, data.approvalsMeta, {
                        loaded: this.visibleApprovalCount(),
                        hasMore: this.visibleApprovalCount() < (data.approvalsMeta.total ?? 0),
                    });
                }

                if (data.sectionApprovalsLabel) {
                    this.sectionApprovalsLabel = data.sectionApprovalsLabel;
                }
            } catch (error) {
                this.toast = error.message || 'Could not refresh queue.';
            } finally {
                this.reloading = false;
            }
        },
        async loadMore() {
            if (this.loadingMore || this.reloading || !this.approvalsMeta.hasMore || !this.approvalsUrl) {
                return;
            }

            this.loadingMore = true;
            this.toast = null;

            try {
                const response = await fetch(this.approvalsQuery(this.visibleApprovalCount()).toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok || !data.ok) {
                    throw new Error(data.message || `Could not load more (${response.status}).`);
                }

                const container = document.getElementById('approval-queue-cards');
                if (container && data.html) {
                    container.querySelector('[data-queue-empty="approvals"]')?.remove();
                    container.insertAdjacentHTML('beforeend', data.html);
                }

                if (data.approvalsMeta) {
                    this.approvalsMeta = Object.assign({}, this.approvalsMeta, data.approvalsMeta, {
                        loaded: this.visibleApprovalCount(),
                        hasMore: this.visibleApprovalCount() < (data.approvalsMeta.total ?? this.approvalsMeta.total),
                    });
                } else {
                    this.syncApprovalsMeta();
                }
            } catch (error) {
                this.toast = error.message || 'Could not load more.';
            } finally {
                this.loadingMore = false;
            }
        },
        async submitAction(event) {
            const form = event?.target instanceof HTMLFormElement
                ? event.target
                : (event?.submitter?.form ?? event?.target?.closest?.('form.queue-action-form'));

            if (!form?.matches?.('form.queue-action-form') || this.submitting) {
                return;
            }

            const url = form.getAttribute('action');
            if (!url) {
                this.toast = 'Action failed: missing form URL.';
                return;
            }

            this.submitting = true;
            this.toast = null;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(form),
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok || !data.ok) {
                    throw new Error(data.message || `Action failed (${response.status}).`);
                }

                if (Array.isArray(data.kpis)) this.kpis = data.kpis;
                if (data.subtitle) this.subtitle = data.subtitle;
                if (data.sectionHumanLabel) this.sectionHumanLabel = data.sectionHumanLabel;
                if (data.sectionExceptionsLabel) this.sectionExceptionsLabel = data.sectionExceptionsLabel;

                const slug = data.removedSlug || form.dataset.queueSlug;
                const removedApproval = !!(slug && document.querySelector(`#approval-queue-cards article[data-queue-slug="${slug}"]`));
                if (slug) {
                    document.querySelector(`#approval-queue-cards article[data-queue-slug="${slug}"]`)?.remove()
                        || document.querySelector(`[data-queue-slug="${slug}"]`)?.remove();
                }

                if (removedApproval) {
                    this.syncApprovalsMeta(Math.max(0, this.approvalsMeta.total - 1));
                    if (this.filter) {
                        this.sectionApprovalsLabel = `${this.approvalsMeta.total} shown · ${data.approvalCount ?? '—'} total · 24-hr SLA`;
                    } else if (data.sectionApprovalsLabel) {
                        this.sectionApprovalsLabel = data.sectionApprovalsLabel;
                    }
                } else if (data.sectionApprovalsLabel && !this.filter) {
                    this.sectionApprovalsLabel = data.sectionApprovalsLabel;
                }

                this.toast = data.message || 'Queue updated.';
                window.dispatchEvent(new CustomEvent('sidebar-badges:refresh'));
            } catch (error) {
                this.toast = error.message || 'Action failed.';
            } finally {
                this.submitting = false;
            }
        },
    };
}
</script>
@endsection
