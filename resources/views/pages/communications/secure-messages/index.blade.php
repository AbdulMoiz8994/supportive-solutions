@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ showNew: false }">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Secure messages</h1>
            <p class="text-sm text-gray-500">Internal HIPAA-safe messaging for office staff and caregivers.</p>
        </div>
        @can('create', \App\Models\SecureMessageThread::class)
            <button @click="showNew = true" class="rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white">New thread</button>
        @endcan
    </div>

    <form method="GET" class="flex gap-2">
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search subject" class="rounded-lg border-gray-200">
        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="unread" value="1" @checked($filters['unread'] ?? false)> Unread only</label>
        <button class="rounded-lg bg-gray-900 text-white px-4 py-2 text-sm">Filter</button>
    </form>

    <div class="rounded-2xl border border-gray-200 bg-white divide-y">
        @forelse($threads as $thread)
            @php $participant = $thread->participants->firstWhere('user_id', auth()->id()); @endphp
            <a href="{{ route('communications.secure-messages.show', $thread) }}" class="block px-4 py-4 hover:bg-gray-50">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-semibold text-gray-900">{{ $thread->subject }}</div>
                        <div class="text-xs text-gray-500 mt-1">{{ $thread->participants->pluck('user.name')->filter()->join(', ') }}</div>
                    </div>
                    <div class="text-xs text-gray-500">
                        @if($participant && ! $participant->last_read_at)
                            <span class="inline-flex rounded-full bg-brand-100 text-brand-700 px-2 py-0.5 font-semibold">Unread</span>
                        @endif
                        {{ $thread->last_message_at?->diffForHumans() }}
                    </div>
                </div>
            </a>
        @empty
            <p class="px-4 py-10 text-center text-gray-500">No secure message threads yet.</p>
        @endforelse
    </div>
    {{ $threads->links() }}

    <div x-show="showNew" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6">
            <h3 class="text-lg font-semibold mb-4">New secure thread</h3>
            <form method="POST" action="{{ route('communications.secure-messages.store') }}" class="space-y-3">
                @csrf
                <input type="text" name="subject" required placeholder="Subject" class="w-full rounded-lg border-gray-200">
                <textarea name="body" rows="4" required placeholder="Message" class="w-full rounded-lg border-gray-200"></textarea>
                <select name="participant_ids[]" multiple required class="w-full rounded-lg border-gray-200 min-h-[120px]">
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="showNew = false" class="rounded-lg border px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" class="rounded-lg bg-brand-500 text-white px-4 py-2 text-sm font-semibold">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
