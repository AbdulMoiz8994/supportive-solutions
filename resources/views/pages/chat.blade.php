@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Chat Page" />
    <div class="h-[calc(100vh-186px)] overflow-hidden sm:h-[calc(100vh-174px)]">
        <div class="flex flex-col h-full gap-6 xl:flex-row xl:gap-5">
            {{-- Chat Sidebar Start --}}
            <x-chat.chat-sidebar />
            {{-- Chat Box Start --}}
            <x-chat.chat-box />
            {{-- Chat Box End --}}
        </div>
    </div>
@endsection
