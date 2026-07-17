@props([
    'id' => 'datepicker-' . uniqid(),
    'mode' => 'single',
    'defaultDate' => null,
    'label' => null,
    'placeholder' => 'Select date',
    'name' => null,
    'dateFormat' => 'Y-m-d',
    'enableTime' => false,
    'noCalendar' => false,
])

@php
    $isTimeOnly = $noCalendar && $enableTime;
    $flatpickrMode = ($mode === 'time' || $isTimeOnly) ? 'single' : $mode;
    $inputValue = null;

    if (filled($defaultDate)) {
        if ($isTimeOnly) {
            try {
                $inputValue = \Carbon\Carbon::parse($defaultDate)->format('h:i A');
            } catch (\Throwable) {
                $inputValue = is_string($defaultDate) ? $defaultDate : null;
            }
        } else {
            $inputValue = is_array($defaultDate) ? null : $defaultDate;
        }
    }

    $pickerConfig = [
        'isTimeOnly' => $isTimeOnly,
        'flatpickrMode' => $flatpickrMode,
        'dateFormat' => $dateFormat,
        'enableTime' => (bool) $enableTime,
        'noCalendar' => (bool) $noCalendar,
        'defaultDate' => $inputValue,
    ];
@endphp

<div
    x-data="formDatePicker(@js($pickerConfig))"
    x-init="init()"
    x-destroy="destroy()"
>
    @if($label)
        <label for="{{ $id }}" class="block text-[13px] font-bold text-[#1e293b] mb-2">
            {{ $label }}
        </label>
    @endif

    <div class="relative custom-datepicker">
        <input
            x-ref="dateInput"
            type="text"
            id="{{ $id }}"
            name="{{ $name }}"
            value="{{ $inputValue }}"
            placeholder="{{ $placeholder }}"
            class="w-full px-4 py-3 rounded-[10px] border border-[#e2e8f0] text-[13px] font-medium bg-white placeholder:text-gray-300 focus:border-blue-200 focus:ring-2 focus:ring-blue-50 outline-none transition-all"
            autocomplete="off"
            @if ($attributes->has('required')) required @endif
        />
        <span class="absolute text-gray-500 -translate-y-1/2 pointer-events-none right-3 top-1/2 dark:text-gray-400">
            @if($noCalendar)
                <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" class="size-5">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 21C16.9706 21 21 16.9706 21 12C21 7.02944 16.9706 3 12 3C7.02944 3 3 7.02944 3 12C3 16.9706 7.02944 21 12 21ZM12 22.5C17.799 22.5 22.5 17.799 22.5 12C22.5 6.20101 17.799 1.5 12 1.5C6.20101 1.5 1.5 6.20101 1.5 12C1.5 17.799 6.20101 22.5 12 22.5ZM12.75 6V11.6893L16.2803 15.2197C16.5732 15.5126 16.5732 15.9874 16.2803 16.2803C15.9874 16.5732 15.5126 16.5732 15.2197 16.2803L11.4697 12.5303C11.329 12.3897 11.25 12.1989 11.25 12V6C11.25 5.58579 11.5858 5.25 12 5.25C12.4142 5.25 12.75 5.58579 12.75 6Z" fill="currentColor"></path>
                </svg>
            @else
                <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" class="size-5">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M8 2C8.41421 2 8.75 2.33579 8.75 2.75V3.75H15.25V2.75C15.25 2.33579 15.5858 2 16 2C16.4142 2 16.75 2.33579 16.75 2.75V3.75H18.5C19.7426 3.75 20.75 4.75736 20.75 6V9V19C20.75 20.2426 19.7426 21.25 18.5 21.25H5.5C4.25736 21.25 3.25 20.2426 3.25 19V9V6C3.25 4.75736 4.25736 3.75 5.5 3.75H7.25V2.75C7.25 2.33579 7.58579 2 8 2ZM8 5.25H5.5C5.08579 5.25 4.75 5.58579 4.75 6V8.25H19.25V6C19.25 5.58579 18.9142 5.25 18.5 5.25H16H8ZM19.25 9.75H4.75V19C4.75 19.4142 5.08579 19.75 5.5 19.75H18.5C18.9142 19.75 19.25 19.4142 19.25 19V9.75Z" fill="currentColor"></path>
                </svg>
            @endif
        </span>
    </div>
</div>
