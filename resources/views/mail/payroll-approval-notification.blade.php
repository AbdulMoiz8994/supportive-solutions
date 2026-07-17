Payroll batch approved in {{ config('app.name') }}

Batch ID: #{{ $batch->id }}
Pay period: {{ $batch->period_key }}
Approved by: {{ $approvedBy->name }} ({{ $approvedBy->email }})
Approved at: {{ optional($batch->approved_at)->timezone(config('app.timezone'))->format('M j, Y g:i A T') }}

Caregivers ready for AccountantsWorld: {{ $readyCount }}
Caregivers on hold (billing/review): {{ $heldCount }}
Total gross (batch): ${{ number_format((float) $batch->total_gross, 2) }}

@if($batch->approval_note)
Approver note:
{{ $batch->approval_note }}

@endif
Next step: review the batch in AccountantsWorld and process payroll. Pay stubs should be linked back to each caregiver profile once generated.

Open AccountantsWorld: {{ config('payroll.accountants_world_url') }}
