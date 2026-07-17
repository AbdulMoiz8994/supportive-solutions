<?php

namespace App\Http\Requests\Schedule\Concerns;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Validation\Rule;

trait ValidatesScheduleFields
{
    protected function prepareForValidation(): void
    {
        if ($this->has('all_day')) {
            $this->merge([
                'all_day' => filter_var($this->input('all_day'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->filled('status')) {
            $this->merge([
                'status' => Schedule::normalizeStatus((string) $this->input('status')),
            ]);
        }

        foreach (['start_time', 'end_time'] as $field) {
            if (! $this->filled($field)) {
                continue;
            }

            try {
                $this->merge([
                    $field => \Carbon\Carbon::parse($this->input($field))->format('H:i:s'),
                ]);
            } catch (\Throwable) {
                // Leave the original value so validation can surface a clear error.
            }
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function scheduleFieldRules(bool $requireStatus = false): array
    {
        $organizationId = $this->user()?->organization_id;

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'event_type' => ['required', 'string', Rule::in(Schedule::eventTypes())],
            'client_id' => [
                'nullable',
                Rule::exists('clients', 'id')->where(
                    fn ($query) => $organizationId
                        ? $query->where('organization_id', $organizationId)
                        : $query
                ),
            ],
            'employee_id' => [
                'nullable',
                Rule::exists('employees', 'id')->where(
                    fn ($query) => $organizationId
                        ? $query->where('organization_id', $organizationId)
                        : $query
                ),
            ],
            'date' => ['required', 'date'],
            'start_time' => ['required'],
            'end_time' => ['required'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:255'],
            'all_day' => ['sometimes', 'boolean'],
        ];

        if ($requireStatus) {
            $rules['status'] = ['required', 'string', Rule::in(Schedule::statuses())];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $eventType = $this->input('event_type');

            if ($eventType === Schedule::EVENT_CARE_VISIT) {
                if (! $this->filled('client_id')) {
                    $validator->errors()->add('client_id', 'A client is required for care visit events.');
                }

                if (! $this->filled('employee_id')) {
                    $validator->errors()->add('employee_id', 'A caregiver is required for care visit events.');
                }
            }

            if ($this->filled('date') && $this->filled('start_time') && $this->filled('end_time')) {
                try {
                    $start = \Carbon\Carbon::parse($this->input('date').' '.$this->input('start_time'));
                    $end = \Carbon\Carbon::parse($this->input('date').' '.$this->input('end_time'));

                    if ($end->lessThanOrEqualTo($start) && ! filter_var($this->input('all_day'), FILTER_VALIDATE_BOOLEAN)) {
                        $validator->errors()->add('end_time', 'End time must be after start time.');
                    } elseif (! $validator->errors()->has('end_time')) {
                        if ($end->lessThanOrEqualTo($start)) {
                            $end = $end->copy()->addDay();
                        }

                        $conflict = Schedule::conflictFor(
                            $this->filled('employee_id') ? (int) $this->input('employee_id') : null,
                            $this->filled('client_id') ? (int) $this->input('client_id') : null,
                            $start,
                            $end,
                            $this->route('id') ? (int) $this->route('id') : null,
                        );

                        if ($conflict === 'caregiver') {
                            $validator->errors()->add('employee_id', 'This caregiver already has an overlapping visit scheduled at this time.');
                        } elseif ($conflict === 'client') {
                            $validator->errors()->add('client_id', 'This client already has an overlapping visit scheduled at this time.');
                        }
                    }
                } catch (\Throwable) {
                    $validator->errors()->add('start_time', 'Invalid start or end time.');
                }
            }
        });
    }
}
