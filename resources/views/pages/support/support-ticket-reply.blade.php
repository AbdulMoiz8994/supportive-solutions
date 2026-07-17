@extends('layouts.app')

@php
   // Sample data - In real application, pass this from controller
   $ticket = [
       'id' => '#346520',
       'subject' => 'Sidebar not responsive on mobile',
       'customer_name' => 'John Doe',
       'customer_email' => 'jhondelin@gmail.com',
       'category' => 'General Support',
       'status' => 'In Progress',
       'status_class' => 'bg-blue-light-50 dark:bg-blue-light-500/15 text-blue-light-500 dark:text-blue-light-500',
       'created_at' => 'Dec 20, 2028',
       'date' => 'Mon, 3:20 PM (2 days ago)',
       'current' => 4,
       'total' => 120,
   ];

   $messages = [
       [
           'id' => 1,
           'author' => 'John Doe',
           'email' => 'jhondelin@gmail.com',
           'avatar' => './images/support/user-1.jpg',
           'time' => 'Mon, 3:20 PM (2 hrs ago)',
           'is_support' => false,
           'content' => [
               'Hi TailAdmin Team,',
               'I hope you\'re doing well.',
               'I\'m currently working on customizing the TailAdmin dashboard and would like to add a new section labeled "Reports." Before I proceed, I wanted to check if there\'s any official guide or best practice you recommend for adding custom pages within the TailAdmin structure.',
           ],
       ],
       [
           'id' => 2,
           'author' => 'Musharof Chowdhury',
           'team_label' => 'From - tailadmin support team',
           'avatar' => './images/support/user-2.jpg',
           'time' => 'Mon, 3:20 PM (2 hrs ago)',
           'is_support' => true,
           'content' => [
               'Hi John D,',
               'Thanks for reaching out—and great to hear you\'re customizing TailAdmin to fit your needs! Yes, you can definitely add custom pages like a "Reports" section, and it\'s quite straightforward. Here\'s a quick guide to help you get started:',
               'To include your new page in the sidebar:',
           ],
           'list_items' => [
               'To include your new page in the sidebar: Go to the sidebar configuration file (sidebarData.ts or similar)',
               'Add a new entry with the label "Reports" and route /reports',
           ],
       ],
       [
           'id' => 3,
           'author' => 'John Doe',
           'email' => 'jhondelin@gmail.com',
           'avatar' => './images/support/user-1.jpg',
           'time' => 'Mon, 3:20 PM (2 hrs ago)',
           'is_support' => false,
           'content' => [
               'Hi TailAdmin Team,',
               'I hope you\'re doing well.',
               'I\'m currently working on customizing the TailAdmin dashboard and would like to add a new section labeled "Reports." Before I proceed, I wanted to check if there\'s any official guide or best practice you recommend for adding custom pages within the TailAdmin structure.',
           ],
       ],
   ];

   $statusOptions = [
       ['value' => 'in-progress', 'label' => 'In-Progress'],
       ['value' => 'solved', 'label' => 'Solved'],
       ['value' => 'on-hold', 'label' => 'On-Hold'],
   ];

   $ticketDetails = [
       ['label' => 'Customer', 'value' => $ticket['customer_name']],
       ['label' => 'Email', 'value' => $ticket['customer_email']],
       ['label' => 'Ticket ID', 'value' => $ticket['id']],
       ['label' => 'Category', 'value' => $ticket['category']],
       ['label' => 'Created', 'value' => $ticket['created_at']],
       ['label' => 'Status', 'value' => $ticket['status'], 'is_badge' => true],
   ];
@endphp
@section('content')
    <x-common.page-breadcrumb pageTitle="Support Ticket reply" />

<div class="overflow-hidden xl:h-[calc(100vh-180px)]">
    <div class="grid h-full grid-cols-1 gap-5 xl:grid-cols-12">
        <!-- Left -->
        <div class="xl:col-span-8 2xl:col-span-9">
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <!-- Header -->
                <div class="flex flex-col justify-between gap-5 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center dark:border-gray-800">
                    <div>
                        <h3 class="text-lg font-medium text-gray-800 dark:text-white/90">
                            Ticket {{ $ticket['id'] }} - {{ $ticket['subject'] }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $ticket['date'] }}
                        </p>
                    </div>
                    <div class="flex items-center gap-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $ticket['current'] }} of {{ $ticket['total'] }}
                        </p>
                        <div class="flex items-center gap-2">
                            <button class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400 dark:hover:bg-gray-900 dark:hover:text-white/90">
                                <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12.7083 5L7.5 10.2083L12.7083 15.4167" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <button class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400 dark:hover:bg-gray-900 dark:hover:text-white/90">
                                <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M7.29167 15.8335L12.5 10.6252L7.29167 5.41683" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="relative px-6 py-7">
                    <div class="custom-scrollbar h-[calc(58vh-162px)] space-y-7 divide-y divide-gray-200 overflow-y-auto pr-2 dark:divide-gray-800">
                        @foreach ($messages as $message)
                            <article class="{{ $loop->first ? '' : 'pt-7' }}">
                                <div class="mb-6 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $message['avatar'] }}" class="h-10 w-10 shrink-0 rounded-full" alt="{{ $message['author'] }}">
                                        <div>
                                            <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                                {{ $message['author'] }}
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                @if ($message['is_support'])
                                                    {{ $message['team_label'] }}
                                                @else
                                                    {{ $message['email'] }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $message['time'] }}
                                        </p>
                                    </div>
                                </div>
                                <div class="pb-6">
                                    @foreach ($message['content'] as $paragraph)
                                        <p class="{{ $loop->last ? '' : 'mb-5' }} text-sm text-gray-500 dark:text-gray-400">
                                            {{ $paragraph }}
                                        </p>
                                    @endforeach

                                    @if (isset($message['list_items']))
                                        <ul class="list-inside list-disc pl-2 text-sm text-gray-500 dark:text-gray-400">
                                            @foreach ($message['list_items'] as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <!-- Fixed Input Wrapper -->
                    <div class="pt-5">
                        <form>
                            @csrf
                            <div class="mx-auto max-h-[162px] w-full rounded-2xl border border-gray-200 shadow-xs dark:border-gray-800 dark:bg-gray-800">
                                <textarea name="message" placeholder="Type your reply here..." class="h-20 w-full resize-none border-none bg-transparent p-5 font-normal text-gray-800 outline-none placeholder:text-gray-400 focus:ring-0 dark:text-white"></textarea>

                                <div class="flex items-center justify-between p-3">
                                    <button type="button" class="flex h-9 items-center gap-1.5 rounded-lg bg-transparent px-2 py-3 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-900 dark:hover:text-gray-300">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none">
                                            <path d="M14.4194 11.7679L15.4506 10.7367C17.1591 9.02811 17.1591 6.25802 15.4506 4.54947C13.742 2.84093 10.9719 2.84093 9.2634 4.54947L8.2322 5.58067M11.77 14.4172L10.7365 15.4507C9.02799 17.1592 6.2579 17.1592 4.54935 15.4507C2.84081 13.7422 2.84081 10.9721 4.54935 9.26352L5.58285 8.23002M11.7677 8.23232L8.2322 11.7679" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        Attach
                                    </button>
                                    <button type="submit" class="bg-brand-500 hover:bg-brand-600 shadow-theme-xs inline-flex h-9 items-center justify-center rounded-lg px-4 py-3 text-sm font-medium text-white">
                                        Reply
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Status -->
                    <div class="mt-6 flex flex-wrap items-center gap-4">
                        <span class="text-gray-500 dark:text-gray-400">Status:</span>
                        <div x-data="{ selected: 'in-progress' }" class="flex items-center gap-4">
                            @foreach ($statusOptions as $option)
                                <label for="radio{{ $option['value'] }}" class="flex cursor-pointer items-center text-sm font-medium text-gray-700 select-none dark:text-gray-400">
                                    <div class="relative">
                                        <input type="radio" id="radio{{ $option['value'] }}" value="{{ $option['value'] }}" x-model="selected" class="sr-only">
                                        <div :class="selected === '{{ $option['value'] }}' ? 'border-brand-500 bg-brand-500' : 'bg-transparent border-gray-300 dark:border-gray-700'" class="hover:border-brand-500 dark:hover:border-brand-500 mr-3 flex h-4 w-4 items-center justify-center rounded-full border-[1.25px]">
                                            <span class="h-1.5 w-1.5 rounded-full" :class="selected === '{{ $option['value'] }}' ? 'bg-white' : 'bg-white dark:bg-[#171f2e]'"></span>
                                        </div>
                                    </div>
                                    {{ $option['label'] }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right -->
        <div class="xl:col-span-4 2xl:col-span-3">
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                    <h3 class="text-lg font-medium text-gray-800 dark:text-white/90">
                        Ticket Details
                    </h3>
                </div>
                <ul class="divide-y divide-gray-100 px-6 py-3 dark:divide-gray-800">
                    @foreach ($ticketDetails as $detail)
                        <li class="grid grid-cols-2 gap-5 py-2.5">
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $detail['label'] }}</span>
                            @if (isset($detail['is_badge']) && $detail['is_badge'])
                                <div>
                                    <span class="{{ $ticket['status_class'] }} text-theme-xs inline-block rounded-full px-2 py-0.5 font-medium">
                                        {{ $detail['value'] }}
                                    </span>
                                </div>
                            @else
                                <span class="text-sm {{ $detail['label'] === 'Email' ? 'break-words' : '' }} text-gray-700 dark:text-gray-400">
                                    {{ $detail['value'] }}
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
