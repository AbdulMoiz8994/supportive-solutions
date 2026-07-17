@props(['invoices' => [], 'title' => 'Recent Invoices'])

@php
    $defaultInvoices = [
        [
            'serial_no' => '#DF429',
            'close_date' => 'April 28, 2016',
            'user' => 'Jenny Wilson',
            'amount' => '$473.85',
            'status' => 'Complete',
        ],
        [
            'serial_no' => '#HTY274',
            'close_date' => 'October 30, 2017',
            'user' => 'Wade Warren',
            'amount' => '$293.01',
            'status' => 'Complete',
        ],
        [
            'serial_no' => '#LKE600',
            'close_date' => 'May 29, 2017',
            'user' => 'Darlene Robertson',
            'amount' => '$782.01',
            'status' => 'Pending',
        ],
        [
            'serial_no' => '#HRP447',
            'close_date' => 'May 20, 2015',
            'user' => 'Arlene McCoy',
            'amount' => '$202.87',
            'status' => 'Cancelled',
        ],
        [
            'serial_no' => '#WRH647',
            'close_date' => 'March 13, 2014',
            'user' => 'Bessie Cooper',
            'amount' => '$490.51',
            'status' => 'Complete',
        ],
    ];
    
    $invoicesList = !empty($invoices) ? $invoices : $defaultInvoices;
    
    $getStatusClass = function($status) {
        return match(strtolower($status)) {
            'complete' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500',
            'pending' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-500',
            'cancelled', 'canceled' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500',
            default => 'bg-gray-50 text-gray-600 dark:bg-gray-500/15 dark:text-gray-400',
        };
    };
@endphp

<div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="px-6 py-4">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h3>
    </div>
    <div class="custom-scrollbar overflow-x-auto">
        <table class="min-w-full">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-900">
                    <th class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                        Serial No:
                    </th>
                    <th class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                        Close Date
                    </th>
                    <th class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                        User
                    </th>
                    <th class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                        Amount
                    </th>
                    <th class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                        Status
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse($invoicesList as $invoice)
                    <tr>
                        <td class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                            {{ $invoice['serial_no'] }}
                        </td>
                        <td class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                            {{ $invoice['close_date'] }}
                        </td>
                        <td class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                            {{ $invoice['user'] }}
                        </td>
                        <td class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                            {{ $invoice['amount'] }}
                        </td>
                        <td class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                            <span class="{{ $getStatusClass($invoice['status']) }} text-theme-xs rounded-full px-2 py-0.5 font-medium">
                                {{ $invoice['status'] }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-400">
                            No invoices found
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>