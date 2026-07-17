@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <a href="{{ route('communications.secure-messages.index') }}" class="text-sm text-gray-500">← Secure messages</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-2">{{ $thread->subject }}</h1>
        <p class="text-sm text-gray-500">Participants: {{ $thread->participants->map(fn($p) => $p->user?->name)->filter()->join(', ') }}</p>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-4 space-y-4 max-h-[60vh] overflow-y-auto">
        @foreach($thread->messages as $message)
            <div class="flex {{ $message->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[80%] rounded-2xl px-4 py-3 {{ $message->sender_id === auth()->id() ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-800' }}">
                    <div class="text-xs opacity-80 mb-1">{{ $message->sender?->name }} · {{ $message->created_at?->format('g:i A') }}</div>
                    <div class="whitespace-pre-wrap text-sm">{{ e($message->body) }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @can('reply', $thread)
        <form method="POST" action="{{ route('communications.secure-messages.reply', $thread) }}" class="rounded-2xl border border-gray-200 bg-white p-4 flex gap-3">
            @csrf
            <textarea name="body" rows="2" required class="flex-1 rounded-lg border-gray-200" placeholder="Reply securely"></textarea>
            <button type="submit" class="rounded-lg bg-brand-500 text-white px-4 py-2 text-sm font-semibold self-end">Send</button>
        </form>
    @endcan
</div>
@endsection
