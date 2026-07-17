@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Chat with {{ $contact->first_name }}" />

    <div class="flex flex-col h-[calc(100vh-250px)] max-w-5xl mx-auto bg-white rounded-2xl shadow-theme-xs border border-gray-100 overflow-hidden dark:bg-white/[0.03] dark:border-white/[0.05]">
        <!-- Chat Header -->
        <div class="p-4 border-b border-gray-50 flex items-center justify-between dark:border-white/[0.02]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 flex items-center justify-center bg-brand-500/10 text-brand-500 rounded-full font-bold">
                    {{ substr($contact->first_name, 0, 1) }}
                </div>
                <div>
                    <h4 class="font-bold text-gray-800 dark:text-white/90">{{ $contact->first_name }} {{ $contact->last_name }}</h4>
                    <span class="text-[10px] text-green-500 font-bold uppercase">Online Now</span>
                </div>
            </div>
            <a href="{{ route('messages') }}" class="text-xs text-gray-500 hover:text-brand-500">Back to Contacts</a>
        </div>

        <!-- Messages Area -->
        <div class="flex-1 overflow-y-auto p-6 space-y-4 bg-gray-50/50 dark:bg-transparent">
            @forelse($messages as $msg)
                <div class="flex {{ $msg->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[70%] p-3.5 {{ $msg->sender_id === auth()->id() ? 'bg-brand-500 text-white rounded-2xl rounded-tr-none' : 'bg-white border border-gray-100 text-gray-800 rounded-2xl rounded-tl-none dark:bg-gray-800 dark:border-white/5 dark:text-white' }} shadow-sm">
                        <p class="text-sm leading-relaxed">{{ $msg->content }}</p>
                        <div class="mt-1.5 flex items-center justify-end gap-1 text-[9px] {{ $msg->sender_id === auth()->id() ? 'text-white/70' : 'text-gray-400' }}">
                            {{ $msg->created_at->format('h:i A') }}
                            @if($msg->sender_id === auth()->id())
                                <svg class="w-3 h-3 {{ $msg->read_at ? 'text-white' : 'text-white/40' }}" fill="currentColor" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"></path></svg>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center h-full text-center">
                    <p class="text-gray-400 italic text-sm">This is the start of your secure conversation.</p>
                </div>
            @endforelse
        </div>

        <!-- Input Area -->
        <div class="p-4 border-t border-gray-50 dark:border-white/[0.02]">
            <form action="{{ route('messages.send', $contact->id) }}" method="POST" class="flex gap-2">
                @csrf
                <input type="text" name="content" autocomplete="off" required 
                       class="flex-1 p-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-900/50 dark:border-white/[0.05]" 
                       placeholder="Type your message here...">
                <button type="submit" class="px-6 bg-brand-500 text-white rounded-xl font-bold hover:bg-brand-600 transition-all shadow-lg shadow-brand-500/20">
                    Send
                </button>
            </form>
        </div>
    </div>
@endsection
