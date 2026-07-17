<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SetupAccountRequest;
use App\Http\Requests\Auth\WebLoginRequest;
use App\Models\User;
use App\Services\GlobalSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        protected GlobalSettingsService $settingsService
    ) {}

    /**
     * Show the registration form.
     */
    public function showRegistrationForm()
    {
        if (! config('auth.allow_public_registration', false)) {
            return redirect()
                ->route('signin')
                ->with('error', 'Public registration is disabled. Please contact your administrator.');
        }

        return view('pages.auth.signup', ['title' => 'Sign Up']);
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->fname.' '.$request->lname,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Auth::login($user);

        \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\WelcomeEmail($user));

        return redirect()->route('signin')->with('success', 'Registration successful! Please sign in.');
    }

    /**
     * Show the login form.
     */
    public function showLoginForm()
    {
        return view('pages.auth.signin', ['title' => 'Sign In']);
    }

    /**
     * Handle an authentication attempt.
     */
    public function login(WebLoginRequest $request)
    {
        $credentials = $request->only(['email', 'password']);
        $credentials['is_active'] = true;

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            \App\Models\ActivityLog::create([
                'user_id' => auth()->id(),
                'organization_id' => auth()->user()->organization_id ?? 1,
                'action' => 'User Login',
                'subject_type' => 'App\Models\User',
                'subject_id' => auth()->id(),
                'description' => 'Staff member logged into the dashboard',
                'ip_address' => $request->ip(),
            ]);

            $default = \App\Helpers\MenuHelper::getLandingRoute();
            $intended = $request->session()->get('url.intended', $default);
            $request->session()->put('url.intended', $intended);

            if ($this->pendingTwoFactor(auth()->user())) {
                return redirect()->route('two-factor.choice');
            }

            $request->session()->forget('url.intended');

            return redirect()->to($intended);
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Show the account setup page.
     */
    public function showSetupForm(Request $request)
    {
        $email = $request->query('email');
        $token = $request->query('token');

        $user = User::where('email', $email)
            ->where('invite_token', $token)
            ->where('invite_expires_at', '>', now())
            ->first();

        if (!$user) {
            return redirect()->route('signin')->with('error', 'The activation link is invalid or has expired.');
        }

        return view('pages.auth.setup-account', [
            'email' => $email,
            'token' => $token,
            'title' => 'Setup Account'
        ]);
    }

    /**
     * Store the setup password.
     */
    public function storeSetup(SetupAccountRequest $request)
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])
            ->where('invite_token', $validated['token'])
            ->where('invite_expires_at', '>', now())
            ->first();

        if (!$user) {
            return redirect()->route('signin')->with('error', 'The activation link is invalid or has expired.');
        }

        $user->update([
            'password'               => Hash::make($validated['password']),
            'invite_token'           => null,
            'invite_expires_at'      => null,
            'email_verified_at'      => now(),
            'two_factor_verified_at' => now(), // Mark as verified since they used a secure invite link
            'is_active'              => true,
        ]);

        Auth::login($user);

        \App\Models\ActivityLog::create([
            'user_id' => $user->id,
            'organization_id' => $user->organization_id ?? 1,
            'action' => 'Account Activated',
            'subject_type' => 'App\Models\User',
            'subject_id' => $user->id,
            'description' => 'Staff member completed account setup',
            'ip_address' => $request->ip(),
        ]);

        // Mark 2FA as verified for initial setup login to improve UX
        session(['2fa_verified' => true]);

        return redirect(\App\Helpers\MenuHelper::getLandingRoute())->with('success', 'Account activated successfully!');
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    protected function pendingTwoFactor(User $user): bool
    {
        if (session('2fa_verified')) {
            return false;
        }

        // Master switch (TWO_FACTOR_ENFORCED=false) — simple login, no 2FA.
        if (! config('two_factor.enforced', true)) {
            return false;
        }

        // Testing-only per-account exemption (see config/two_factor.php).
        $email = strtolower((string) $user->email);
        if ($email !== '' && in_array($email, (array) config('two_factor.exempt_emails', []), true)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        try {
            return $this->settingsService->isTwoFactorRequired();
        } catch (\Throwable) {
            return true;
        }
    }
}
