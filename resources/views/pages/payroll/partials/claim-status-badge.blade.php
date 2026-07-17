@props(['status'])

@php
    $classes = match($status) {
        \App\Models\PayrollClaim::STATUS_SUBMITTED,
        \App\Models\PayrollClaim::STATUS_APPROVED => 'bg-green-50 text-green-700 border-green-200',
        \App\Models\PayrollClaim::STATUS_PENDING => 'bg-orange-50 text-orange-700 border-orange-200',
        \App\Models\PayrollClaim::STATUS_FAILED,
        \App\Models\PayrollClaim::STATUS_REJECTED => 'bg-red-50 text-red-700 border-red-200',
        \App\Models\PayrollClaim::STATUS_DRAFT => 'bg-gray-100 text-gray-500 border-gray-200',
        default => 'bg-gray-100 text-gray-600 border-gray-200',
    };
    $label = match($status) {
        \App\Models\PayrollClaim::STATUS_DRAFT => 'Draft',
        \App\Models\PayrollClaim::STATUS_PENDING => 'Pending',
        \App\Models\PayrollClaim::STATUS_SUBMITTED => 'Submitted',
        \App\Models\PayrollClaim::STATUS_APPROVED => 'Approved',
        \App\Models\PayrollClaim::STATUS_REJECTED => 'Rejected',
        \App\Models\PayrollClaim::STATUS_FAILED => 'Failed',
        default => ucfirst(str_replace('_', ' ', (string) $status)),
    };
@endphp

<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $classes }}">{{ $label }}</span>
