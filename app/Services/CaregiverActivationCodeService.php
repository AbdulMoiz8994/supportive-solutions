<?php

namespace App\Services;

use App\Models\CaregiverActivationCode;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class CaregiverActivationCodeService
{
    public function __construct(
        protected GlobalSettingsService $settings,
    ) {}

    public function generate(?Organization $organization, ?int $employeeId, User $actor): CaregiverActivationCode
    {
        abort_if(! $organization, 422, 'No organization configured.');

        $bind = (bool) $this->settings->get('access.bind_code_to_caregiver', true);
        $expiryDays = (int) $this->settings->get('access.code_expiry_days', 7);

        if ($bind && ! $employeeId) {
            throw ValidationException::withMessages([
                'employee_id' => 'Select a caregiver — codes must be bound to a caregiver record.',
            ]);
        }

        $employee = null;
        if ($employeeId) {
            $employee = Employee::query()
                ->where('organization_id', $organization->id)
                ->where('position', 'Caregiver')
                ->findOrFail($employeeId);
        }

        $code = CaregiverActivationCode::create([
            'organization_id' => $organization->id,
            'employee_id' => $employee?->id,
            'code' => CaregiverActivationCode::generateCode(),
            'status' => CaregiverActivationCode::STATUS_PENDING,
            'issued_at' => now(),
            'expires_at' => now()->addDays(max($expiryDays, 1)),
        ]);

        AuditLogQueryService::log($actor, 'Activation code generated', $code, [
            'code' => $code->code,
            'caregiver' => $employee?->first_name,
        ], $organization->id);

        return $code->load('employee');
    }

    public function resend(CaregiverActivationCode $code, User $actor): CaregiverActivationCode
    {
        if ($code->status !== CaregiverActivationCode::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'code' => 'Only pending codes can be resent.',
            ]);
        }

        $expiryDays = (int) $this->settings->get('access.code_expiry_days', 7);
        $code->update([
            'issued_at' => now(),
            'expires_at' => now()->addDays(max($expiryDays, 1)),
        ]);

        $this->notifyCaregiver($code);

        AuditLogQueryService::log($actor, 'Activation code resent', $code, [
            'code' => $code->code,
        ], $code->organization_id);

        return $code->fresh('employee');
    }

    public function revoke(CaregiverActivationCode $code, User $actor): CaregiverActivationCode
    {
        if ($code->status === CaregiverActivationCode::STATUS_ACTIVATED) {
            throw ValidationException::withMessages([
                'code' => 'Activated codes cannot be revoked.',
            ]);
        }

        $code->update(['status' => CaregiverActivationCode::STATUS_REVOKED]);

        AuditLogQueryService::log($actor, 'Activation code revoked', $code, [
            'code' => $code->code,
        ], $code->organization_id);

        return $code->fresh('employee');
    }

    protected function notifyCaregiver(CaregiverActivationCode $code): void
    {
        $email = $code->employee?->email;
        if (! $email) {
            return;
        }

        Mail::raw(
            "Your SSHC caregiver app activation code is: {$code->code}\n\nExpires: ".$code->expires_at?->format('M j, Y'),
            fn ($message) => $message->to($email)->subject('SSHC Caregiver App Activation Code')
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    public function eligibleCaregivers(?Organization $organization)
    {
        if (! $organization) {
            return collect();
        }

        return Employee::query()
            ->where('organization_id', $organization->id)
            ->where('position', 'Caregiver')
            ->where(fn ($q) => $q->where('status', 'Active')->orWhereNull('status'))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email']);
    }
}
