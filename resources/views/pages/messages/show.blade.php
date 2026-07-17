@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <x-common.page-breadcrumb pageTitle="Conversation with {{ $receiver->first_name }}" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 h-[calc(100vh-220px)]">
        <!-- Sidebar: Contacts (Hidden on mobile) -->
        <div class="hidden lg:block lg:col-span-1 border-r border-gray-100 dark:border-white/5 pr-6 overflow-y-auto">
            <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-6">Team Members</h3>
            <div class="space-y-4">
                @foreach($contacts as $contact)
                    <a href="{{ route('messages.show', $contact->id) }}" class="flex items-center gap-3 p-3 rounded-2xl transition-all {{ $contact->id === $receiver->id ? 'bg-brand-500 text-white shadow-brand-xs' : 'hover:bg-gray-50 dark:hover:bg-white/5' }}">
                        <div class="shrink-0 w-10 h-10 rounded-full bg-white/20 flex items-center justify-center font-bold">
                            {{ substr($contact->first_name, 0, 1) }}
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold truncate">{{ $contact->first_name }} {{ $contact->last_name }}</div>
                            <div class="text-[10px] {{ $contact->id === $receiver->id ? 'text-brand-100' : 'text-gray-400' }} uppercase font-bold">{{ $contact->role }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="col-span-1 lg:col-span-3 flex flex-col bg-white dark:bg-white/[0.03] rounded-3xl border border-gray-100 dark:border-white/5 shadow-theme-xs overflow-hidden">
            <!-- Chat Header -->
            <div class="p-6 border-b border-gray-100 dark:border-white/5 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-brand-500/10 text-brand-500 rounded-full flex items-center justify-center font-black text-lg">
                        {{ substr($receiver->first_name, 0, 1) }}
                    </div>
                    <div>
                        <h4 class="text-base font-bold text-gray-800 dark:text-white/90">{{ $receiver->first_name }} {{ $receiver->last_name }}</h4>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            <span class="text-[10px] text-gray-400 font-bold uppercase">{{ $receiver->role }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages Stream -->
            <div class="flex-1 overflow-y-auto p-6 space-y-6 bg-gray-50/30 dark:bg-transparent" id="message-container">
                @foreach($messages as $msg)
                    <div class="flex {{ $msg->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[80%]">
                            <div class="px-5 py-3 rounded-2xl shadow-theme-xs {{ $msg->sender_id === auth()->id() ? 'bg-brand-600 text-white rounded-tr-none' : 'bg-white dark:bg-white/5 text-gray-800 dark:text-white/90 rounded-tl-none border border-gray-100 dark:border-white/5' }}">
                                <p class="text-sm leading-relaxed">{{ $msg->content }}</p>
                            </div>
                            <div class="mt-2 text-[10px] font-bold text-gray-400 uppercase tracking-tighter {{ $msg->sender_id === auth()->id() ? 'text-right' : 'text-left' }}">
                                {{ $msg->created_at->diffForHumans() }}
                                @if($msg->sender_id === auth()->id() && $msg->read_at)
                                    <span class="ml-1 text-brand-500">Seen</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Message Input -->
            <div class="p-6 border-t border-gray-100 dark:border-white/5 bg-white dark:bg-transparent">
                <form action="{{ route('messages.store') }}" method="POST" class="flex gap-4">
                    @csrf
                    <input type="hidden" name="receiver_id" value="{{ $receiver->id }}">
                    <input type="text" name="content" required placeholder="Type clinical update or agency message..." 
                           class="flex-1 bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl px-6 py-4 text-sm font-medium focus:ring-2 focus:ring-brand-500 outline-none transition-all dark:text-white">
                    <button type="submit" class="shrink-0 w-14 h-14 bg-brand-600 rounded-2xl flex items-center justify-center text-white hover:bg-brand-700 active:scale-95 transition-all shadow-brand-xs">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Auto-scroll to bottom of chat
        const container = document.getElementById('message-container');
        container.scrollTop = container.scrollHeight;
    </script>
@endsection
