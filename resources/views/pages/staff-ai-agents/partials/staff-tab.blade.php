@php
    use App\Services\StaffAiAgentsService;
    $agentsService = app(StaffAiAgentsService::class);
@endphp

<div class="rounded-xl border border-[#e2e8f0] bg-white overflow-hidden">
    <div class="px-4 py-3 border-b border-[#f1f5f9]">
        <h3 class="text-[14px] font-semibold text-[#0f172a]">Users</h3>
        <p class="text-[12px] text-[#94a3b8] mt-0.5">Roles &amp; permissions · most work is done by agents</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="text-[11px] uppercase tracking-wide text-[#94a3b8] bg-[#fcfdfe] border-b border-[#e2e8f0]">
                    <th class="px-4 py-2.5">User</th>
                    <th class="px-4 py-2.5">Role</th>
                    <th class="px-4 py-2.5">Permissions</th>
                    <th class="px-4 py-2.5">2FA</th>
                    <th class="px-4 py-2.5">Last active</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($staffUsers as $user)
                    @php
                        $initials = strtoupper(collect(explode(' ', $user->name))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
                        $rolePill = $user->role === \App\Models\User::ROLE_ADMIN
                            ? 'bg-[#ede9fe] text-[#5b21b6]'
                            : 'bg-[#f1f5f9] text-[#475569]';
                    @endphp
                    <tr class="border-b border-[#f1f5f9] hover:bg-[#f8fafc]">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex w-8 h-8 rounded-lg bg-gradient-to-br from-[#2563eb] to-[#1e40af] text-white text-[11px] font-bold items-center justify-center">{{ $initials }}</span>
                                <div>
                                    <div class="font-semibold text-[#0f172a] text-[13px]">{{ $user->name }}</div>
                                    <div class="text-[10.5px] text-[#94a3b8]">{{ $user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[11.5px] font-semibold {{ $rolePill }}">
                                {{ $agentsService->staffRoleLabel($user) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-[13px] text-[#334155]">{{ $agentsService->staffPermissionsSummary($user) }}</td>
                        <td class="px-4 py-3">
                            @if($agentsService->userHas2fa($user))
                                <x-ui.pill variant="green" size="sm">On</x-ui.pill>
                            @else
                                <x-ui.pill variant="gray" size="sm">Off</x-ui.pill>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-[13px] text-[#334155]">{{ $agentsService->userLastActive($user) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('staff.show', $user->id) }}" class="text-[#2563eb] font-semibold text-[12px] hover:underline">Manage ›</a>
                        </td>
                    </tr>
                @endforeach

                @can('create', \App\Models\User::class)
                <tr class="border-b border-[#f1f5f9] hover:bg-[#f8fafc] cursor-pointer group">
                    <td class="px-4 py-3" colspan="5">
                        <a href="{{ route('staff.create') }}" class="flex items-center gap-2">
                            <span class="inline-flex w-8 h-8 rounded-lg bg-[#dbeafe] text-[#2563eb] text-lg font-bold items-center justify-center group-hover:bg-[#2563eb] group-hover:text-white transition-colors">+</span>
                            <div>
                                <div class="font-semibold text-[#0f172a] text-[13px] group-hover:text-[#2563eb]">Add Staff</div>
                                <div class="text-[10.5px] text-[#94a3b8]">Invite a new admin, approver, or view-only user</div>
                            </div>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('staff.create') }}" class="text-[#2563eb] font-semibold text-[12px] hover:underline">Open form ›</a>
                    </td>
                </tr>
                @endcan
            </tbody>
        </table>
    </div>
</div>
