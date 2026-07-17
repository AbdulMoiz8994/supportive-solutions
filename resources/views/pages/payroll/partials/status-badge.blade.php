@props(['status', 'daysRemaining' => null])

@php
    $classes = match($status) {
        \App\Models\PayRecord::STATUS_READY => 'bg-green-50 text-green-700 border-green-200',
        \App\Models\PayRecord::STATUS_IN_GRACE => 'bg-orange-50 text-orange-700 border-orange-200',
        \App\Models\PayRecord::STATUS_LATE_ROLLED => 'bg-gray-100 text-gray-600 border-gray-200',
        \App\Models\PayRecord::STATUS_HELD => 'bg-red-50 text-red-700 border-red-200',
        \App\Models\PayRecord::STATUS_PAID => 'bg-green-50 text-green-700 border-green-200',
        \App\Models\PayRecord::STATUS_AWAITING_FORM => 'bg-gray-100 text-gray-500 border-gray-200',
        default => 'bg-gray-100 text-gray-600 border-gray-200',
    };
    $label = $status;
    if ($status === \App\Models\PayRecord::STATUS_IN_GRACE && $daysRemaining !== null) {
        $label = "In grace — {$daysRemaining}d left";
    }
@endphp

<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $classes }}">{{ $label }}</span>
