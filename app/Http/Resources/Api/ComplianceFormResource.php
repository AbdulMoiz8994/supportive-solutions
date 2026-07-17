<?php

namespace App\Http\Resources\Api;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\ComplianceForm
 */
class ComplianceFormResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $submitted = $this->submitted_at !== null;
        $overdue = ! $submitted && $this->isPastDue();

        return [
            'id' => $this->id,
            'period' => $this->period,
            'period_label' => $this->period_label ?: $this->derivePeriodLabel(),
            'status' => $submitted ? 'Submitted' : ($overdue ? 'Overdue' : 'Pending'),
            'submitted' => $submitted,
            'is_overdue' => $overdue,
            'service_start' => optional($this->service_start)->toDateString(),
            'service_end' => optional($this->service_end)->toDateString(),
            'required_days_per_week' => $this->required_days_per_week,
            'authorized_hours' => $this->authorized_hours,
            'delivered_hours' => $this->delivered_hours !== null ? (float) $this->delivered_hours : null,
            'additional_notes' => $this->additional_notes,
            'certification' => $this->certification,
            'signature_url' => $this->signature_path ? Storage::disk('public')->url($this->signature_path) : null,
            'submitted_at' => optional($this->submitted_at)->toIso8601String(),
            'time_ago' => optional($this->submitted_at)->diffForHumans(),
        ];
    }

    /** Certification is due by the end of the month after the certified period. */
    private function isPastDue(): bool
    {
        $period = $this->periodDate();

        return $period !== null && now()->greaterThan($period->copy()->addMonth()->endOfMonth());
    }

    private function derivePeriodLabel(): ?string
    {
        return $this->periodDate()?->format('F Y');
    }

    private function periodDate(): ?Carbon
    {
        if (! is_string($this->period) || ! preg_match('/^\d{4}-\d{2}$/', $this->period)) {
            return null;
        }

        return Carbon::createFromFormat('Y-m', $this->period)->startOfMonth();
    }
}
