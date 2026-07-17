@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Messaging Portal" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Contact List -->
        <div class="lg:col-span-1 space-y-4">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white/90 mb-4">Contacts</h3>
            <div class="space-y-3">
                @forelse($contacts as $contact)
                    <a href="{{ route('messages.show', $contact->id) }}" class="flex items-center gap-4 p-4 bg-white rounded-xl shadow-theme-xs border border-gray-100 hover:border-brand-500 hover:shadow-md transition-all dark:bg-white/[0.03] dark:border-white/[0.05]">
                        <div class="w-12 h-12 flex items-center justify-center bg-brand-500/10 text-brand-500 rounded-full font-bold">
                            {{ substr($contact->first_name, 0, 1) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-bold text-gray-800 dark:text-white/90 truncate">{{ $contact->first_name }} {{ $contact->last_name }}</h4>
                            <p class="text-[10px] text-gray-500 uppercase">{{ $contact->role }}</p>
                        </div>
                        <div class="w-2 h-2 bg-brand-500 rounded-full"></div>
                    </a>
                @empty
                    <p class="p-8 text-center text-gray-500 italic">No other users found in your organization.</p>
                @endforelse
            </div>
        </div>

        <!-- Chat Placeholder -->
        <div class="lg:col-span-2 hidden lg:flex flex-col items-center justify-center p-12 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-100 dark:bg-white/[0.01]">
            <div class="w-16 h-16 bg-brand-500/5 text-brand-500 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
            </div>
            <h4 class="text-xl font-bold text-gray-800 dark:text-white/90">Your Messages</h4>
            <p class="text-sm text-gray-500 mt-2 text-center max-w-xs">Select a contact from the list to start a conversation with your agency team.</p>
        </div>
    </div>
@endsection
