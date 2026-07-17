<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCaregiver;
use App\Http\Controllers\Controller;
use App\Support\Api\AvatarUrl;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    use ResolvesCaregiver;

    /**
     * The caregiver profile behind the logged-in mobile account.
     * Backs both the Profile tab and the "Welcome Back" home header.
     */
    public function show(): JsonResponse
    {
        $caregiver = $this->caregiver();

        return response()->json([
            'data' => [
                'id' => $caregiver->id,
                'first_name' => $caregiver->first_name,
                'last_name' => $caregiver->last_name,
                'name' => $caregiver->name,
                'initials' => $this->initials($caregiver->first_name, $caregiver->last_name),
                'email' => $caregiver->email,
                'phone' => $caregiver->phone,
                'address' => $caregiver->address,
                'avatar_url' => AvatarUrl::forPhoto($caregiver->profile_photo),
                'caregiver_type' => $caregiver->caregiver_type,
                'live_in' => (bool) $caregiver->live_in,
                'hourly_wage' => $caregiver->hourly_wage !== null ? (float) $caregiver->hourly_wage : null,
                'status' => $caregiver->status,
                'pay_eligibility_start' => optional($caregiver->pay_eligibility_start)->toDateString(),
            ],
        ]);
    }

    private function initials(?string $first, ?string $last): string
    {
        return strtoupper(mb_substr((string) $first, 0, 1).mb_substr((string) $last, 0, 1)) ?: 'C';
    }
}
