<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\SendTwoFactorOtpRequest;
use App\Http\Requests\Auth\VerifyTwoFactorRequest;
use App\Services\TwoFactorService;

class TwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorService $twoFactorService
    ) {}

    /**
     * Show the 2FA choice page.
     */
    public function showChoice()
    {
        return view('pages.auth.two-factor-choice', ['title' => 'Two-Factor Authentication']);
    }

    /**
     * Send OTP via the chosen method.
     */
    public function sendOTP(SendTwoFactorOtpRequest $request)
    {
        return $this->twoFactorService->generateAndSend(
            auth()->user(),
            $request->validated('method')
        );
    }

    /**
     * Resend the OTP using the previously selected method.
     */
    public function resendOTP()
    {
        $method = session('2fa_method', 'email');

        return $this->twoFactorService->generateAndSend(auth()->user(), $method);
    }

    /**
     * Show the verify entry page.
     */
    public function showVerify()
    {
        $user = auth()->user();

        if (! $user->two_factor_expires_at || $user->two_factor_expires_at->isPast()) {
            return redirect()
                ->route('two-factor.choice')
                ->with('error', 'Please request a verification code to continue.');
        }

        return view('pages.auth.two-step-verification', ['title' => 'Verify Code']);
    }

    /**
     * Verify the OTP.
     */
    public function verify(VerifyTwoFactorRequest $request)
    {
        $result = $this->twoFactorService->verify(
            auth()->user(),
            $request->validated('otp')
        );

        if ($result['success']) {
            return redirect()->intended(\App\Helpers\MenuHelper::getLandingRoute());
        }

        return back()->with('error', $result['message']);
    }
}
