@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <a href="{{ route('communications.index') }}" class="text-sm text-gray-500">← Communications</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-2">Send request</h1>
        @if($client)
            <p class="text-sm text-gray-500">Client: {{ $client->first_name }} {{ $client->last_name }}</p>
        @endif
    </div>

    <form method="POST" action="{{ $client ? route('communications.client-send', $client->id) : route('communications.send-request.store') }}" enctype="multipart/form-data" class="rounded-2xl border border-gray-200 bg-white p-6 space-y-4">
        @csrf
        @if($client)
            <input type="hidden" name="client_id" value="{{ $client->id }}">
        @endif

        <div>
            <label class="text-sm font-semibold text-gray-700">Template</label>
            <select name="template_id" required class="mt-1 w-full rounded-lg border-gray-200">
                <option value="">Select template</option>
                @foreach($templates as $template)
                    <option value="{{ $template->id }}">{{ $template->name }} ({{ $template->channel }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-sm font-semibold text-gray-700">Subject preview</label>
            <input type="text" name="subject" class="mt-1 w-full rounded-lg border-gray-200" placeholder="Override subject (optional)">
        </div>

        <div>
            <label class="text-sm font-semibold text-gray-700">Body preview</label>
            <textarea name="body" rows="6" class="mt-1 w-full rounded-lg border-gray-200" placeholder="Override body (optional)"></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <input type="email" name="recipient_email" placeholder="Manual recipient email" class="rounded-lg border-gray-200">
            <input type="text" name="recipient_fax" placeholder="Manual recipient fax" class="rounded-lg border-gray-200">
        </div>

        <div>
            <label class="text-sm font-semibold text-gray-700">Attachment</label>
            <input type="file" name="attachment" class="mt-1 w-full text-sm">
        </div>

        <button type="submit" class="rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white">Send request</button>
    </form>
</div>
@endsection
