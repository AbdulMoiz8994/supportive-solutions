@props(['schedule' => null, 'clients', 'employees', 'eventTypes', 'statuses' => null, 'showStatus' => false, 'preselectedClientId' => null, 'preselectedEmployeeId' => null])

<div class="grid grid-cols-1 gap-6 md:grid-cols-2">
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs font-black uppercase tracking-widest text-gray-400">Title <span class="text-red-500">*</span></label>
        <input type="text" name="title" value="{{ old('title', $schedule?->title) }}" required maxlength="255"
               class="w-full rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3 font-bold outline-none transition-all focus:ring-2 focus:ring-brand-500/10 dark:border-white/10 dark:bg-white/5 dark:text-white">
        @error('title')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="mb-2 block text-xs font-black uppercase tracking-widest text-gray-400">Event Type <span class="text-red-500">*</span></label>
        <select name="event_type" required class="w-full rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3 font-bold outline-none dark:border-white/10 dark:bg-white/5 dark:text-white">
            @foreach ($eventTypes as $type)
                <option value="{{ $type }}" @selected(old('event_type', $schedule?->event_type ?? 'care_visit') === $type)>{{ str_replace('_', ' ', ucfirst($type)) }}</option>
            @endforeach
        </select>
        @error('event_type')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
    </div>

    @if ($showStatus && $statuses)
        <div>
            <label class="mb-2 block text-xs font-black uppercase tracking-widest text-gray-400">Status <span class="text-red-500">*</span></label>
            <select name="status" required class="w-full rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3 font-bold outline-none dark:border-white/10 dark:bg-white/5 dark:text-white">
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(old('status', $schedule?->status) === $status)>{{ $status }}</option>
                @endforeach
            </select>
            @error('status')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
    @endif

    <div>
        <label class="mb-2 block text-xs font-black uppercase tracking-widest text-gray-400">Client</label>
        <select name="client_id" class="w-full rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3 font-bold outline-none dark:border-white/10 dark:bg-white/5 dark:text-white">
            <option value="">None</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected((string) old('client_id', $schedule?->client_id ?? $preselectedClientId) === (string) $client->id)>{{ $client->first_name }} {{ $client->last_name }}</option>
            @endforeach
        </select>
        @error('client_id')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="mb-2 block text-xs font-black uppercase tracking-widest text-gray-400">Caregiver / Employee</label>
        <select name="employee_id" class="w-full rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3 font-bold outline-none dark:border-white/10 dark:bg-white/5 dark:text-white">
            <option value="">None</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}" @selected((string) old('employee_id', $schedule?->employee_id ?? $preselectedEmployeeId) === (string) $employee->id)>{{ $employee->first_name }} {{ $employee->last_name }}</option>
            @endforeach
        </select>
        @error('employee_id')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <x-form.date-picker name="date" label="Date" :defaultDate="old('date', $schedule?->date?->format('Y-m-d'))" placeholder="YYYY-MM-DD" required />
        @error('date')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
    </div>

    <div>
        <x-form.date-picker name="start_time" label="Start Time" :enableTime="true" :noCalendar="true" dateFormat="h:i K" placeholder="09:00 AM" :defaultDate="old('start_time', $schedule?->start_time)" required />
        @error('start_time')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
    </div>

    <div>
        <x-form.date-picker name="end_time" label="End Time" :enableTime="true" :noCalendar="true" dateFormat="h:i K" placeholder="05:00 PM" :defaultDate="old('end_time', $schedule?->end_time)" required />
        @error('end_time')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label class="mb-2 block text-xs font-black uppercase tracking-widest text-gray-400">Location / Address</label>
        <input type="text" name="address" value="{{ old('address', $schedule?->address) }}" maxlength="255"
               class="w-full rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3 font-bold outline-none dark:border-white/10 dark:bg-white/5 dark:text-white">
        @error('address')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label class="mb-2 block text-xs font-black uppercase tracking-widest text-gray-400">Description / Notes</label>
        <textarea name="description" rows="4" maxlength="5000"
                  class="w-full rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3 text-sm outline-none dark:border-white/10 dark:bg-white/5 dark:text-white">{{ old('description', $schedule?->description) }}</textarea>
        @error('description')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
    </div>
</div>
