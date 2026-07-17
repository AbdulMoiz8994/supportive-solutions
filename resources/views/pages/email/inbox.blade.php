@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Inbox" />
    <div class="overflow-hidden sm:h-[calc(100vh-174px)] xl:h-[calc(100vh-186px)]">
        <div class="flex h-full flex-col gap-6 sm:gap-5 xl:flex-row">
            <!-- Inbox Sidebar Start -->
            <x-inbox.inbox-sidebar />
            <!-- Inbox Sidebar End -->

            <!-- Inbox Mailbox Start -->
            <x-inbox.inbox-mailbox />
            <!-- Inbox Mailbox End -->
        </div>
    </div>
@endsection
