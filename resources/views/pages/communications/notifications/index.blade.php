@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Notifications</h1>
        <form method="POST" action="{{ route('communications.notifications.read-all') }}">@csrf<button class="text-sm font-semibold text-brand-600">Mark all read</button></form>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white divide-y">
        @forelse($notifications as $notification)
            <div class="px-4 py-4 flex items-start justify-between gap-4 {{ $notification->isUnread() ? 'bg-brand-50/40' : '' }}">
                <div>
                    <div class="font-semibold text-gray-900">{{ $notification->title }}</div>
                    <div class="text-sm text-gray-600 mt-1">{{ $notification->body }}</div>
                    <div class="text-xs text-gray-400 mt-1">{{ $notification->created_at?->diffForHumans() }}</div>
                </div>
                @if($notification->isUnread())
                    <form method="POST" action="{{ route('communications.notifications.read', $notification) }}">@csrf<button class="text-xs font-semibold text-brand-600">Mark read</button></form>
                @endif
            </div>
        @empty
            <p class="px-4 py-10 text-center text-gray-500">No notifications.</p>
        @endforelse
    </div>
    {{ $notifications->links() }}
</div>
@endsection
