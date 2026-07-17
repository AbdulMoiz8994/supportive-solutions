<div x-show="{{ $modal }}" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-2xl rounded-2xl bg-white p-6" @click.away="{{ $modal }} = false">
        <h3 class="text-lg font-semibold mb-4">{{ ($edit ?? false) ? 'Edit template' : 'Add template' }}</h3>
        <form method="POST"
              :action="{{ ($edit ?? false) ? "'{{ url('/communications/templates') }}/' + editTemplate.id" : "'".$action."'" }}"
              class="space-y-3">
            @csrf
            @if(($method ?? 'POST') !== 'POST')
                @method('PUT')
            @endif
            <input type="text" name="name" :value="editTemplate.name" required placeholder="Name" class="w-full rounded-lg border-gray-200">
            <select name="channel" class="w-full rounded-lg border-gray-200">
                @foreach($channels as $channel)
                    <option value="{{ $channel }}">{{ ucfirst($channel) }}</option>
                @endforeach
            </select>
            <select name="recipient_strategy" class="w-full rounded-lg border-gray-200">
                @foreach($strategies as $strategy)
                    <option value="{{ $strategy }}">{{ str_replace('_', ' ', $strategy) }}</option>
                @endforeach
            </select>
            <input type="text" name="subject" :value="editTemplate.subject" placeholder="Subject" class="w-full rounded-lg border-gray-200">
            <textarea name="body" rows="5" required placeholder="Body with @{{ client.first_name }} variables" class="w-full rounded-lg border-gray-200" x-text="editTemplate.body"></textarea>
            <input type="text" name="default_recipient" :value="editTemplate.default_recipient" placeholder="Default recipient (manual/custom)" class="w-full rounded-lg border-gray-200">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked> Active</label>
            <div class="flex justify-end gap-2">
                <button type="button" @click="{{ $modal }} = false" class="rounded-lg border px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-brand-500 text-white px-4 py-2 text-sm font-semibold">Save</button>
            </div>
        </form>
        <p class="text-xs text-gray-500 mt-3">Variables: @foreach($variables as $var) <code>{{ '{{'.$var.'}}' }}</code>@if(!$loop->last), @endif @endforeach</p>
    </div>
</div>
