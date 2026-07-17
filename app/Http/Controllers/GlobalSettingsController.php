<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\GenerateActivationCodeRequest;
use App\Http\Requests\Settings\TestGlobalIntegrationRequest;
use App\Models\IntegrationCredential;
use App\Services\CredentialVaultDraftService;
use App\Http\Requests\Settings\UpdateAgencyIdentityRequest;
use App\Http\Requests\Settings\UpdateCredentialVaultRequest;
use App\Http\Requests\Settings\UpdateGlobalSettingsRequest;
use App\Models\CaregiverActivationCode;
use App\Services\AgencyIdentityService;
use App\Services\AuditLogQueryService;
use App\Services\CaregiverActivationCodeService;
use App\Services\CredentialVaultService;
use App\Services\GlobalIntegrationTestService;
use App\Services\GlobalSettingsPresenterService;
use App\Services\GlobalSettingsService;
use App\Services\HHA\HHAExchangeClient;
use App\Support\TabbedPageTitle;
use Illuminate\Http\JsonResponse;

class GlobalSettingsController extends Controller
{
    public function __construct(
        protected GlobalSettingsService $settingsService,
        protected GlobalSettingsPresenterService $presenter,
        protected AgencyIdentityService $agencyIdentity,
        protected CredentialVaultService $credentialVault,
        protected HHAExchangeClient $hhaClient,
        protected GlobalIntegrationTestService $integrationTests,
        protected CaregiverActivationCodeService $activationCodes,
        protected AuditLogQueryService $auditLog,
    ) {}

    public function index()
    {
        $this->authorize('managePlatformUsers', \App\Models\User::class);

        $organization = $this->agencyIdentity->primaryOrganization();
        $hhaConnection = $this->hhaClient->getConnectionStatus();

        return view('pages.global-settings.index', [
            'title' => TabbedPageTitle::globalSettings(request('tab')),
            'settings' => $this->settingsService->all(),
            'definitions' => $this->settingsService->definitionsForView(),
            'organization' => $organization,
            'credentialVault' => $this->credentialVault->summaryForView(),
            'hhaConnection' => $hhaConnection,
            'presenter' => $this->presenter->forIndex($organization, $hhaConnection),
            'eligibleCaregivers' => $this->activationCodes->eligibleCaregivers($organization),
        ]);
    }

    public function auditLog()
    {
        $this->authorize('managePlatformUsers', \App\Models\User::class);

        $organization = $this->agencyIdentity->primaryOrganization();

        return view('pages.global-settings.audit-log', [
            'title' => 'Audit Log',
            'entries' => $this->auditLog->paginate($organization?->id),
        ]);
    }

    public function generateActivationCode(GenerateActivationCodeRequest $request)
    {
        $this->authorize('managePlatformUsers', \App\Models\User::class);

        $organization = $this->agencyIdentity->primaryOrganization();
        $this->activationCodes->generate(
            $organization,
            $request->validated('employee_id'),
            $request->user(),
        );

        return redirect()
            ->route('settings.global', ['tab' => 'access-activation'])
            ->with('success', 'Activation code generated.');
    }

    public function resendActivationCode(CaregiverActivationCode $activationCode)
    {
        $this->authorize('managePlatformUsers', \App\Models\User::class);

        $this->activationCodes->resend($activationCode, auth()->user());

        return redirect()
            ->route('settings.global', ['tab' => 'access-activation'])
            ->with('success', 'Activation code resent.');
    }

    public function revokeActivationCode(CaregiverActivationCode $activationCode)
    {
        $this->authorize('managePlatformUsers', \App\Models\User::class);

        $this->activationCodes->revoke($activationCode, auth()->user());

        return redirect()
            ->route('settings.global', ['tab' => 'access-activation'])
            ->with('success', 'Activation code revoked.');
    }

    public function update(UpdateGlobalSettingsRequest $request)
    {
        $this->settingsService->update($request->validatedFlat());

        app(\App\Services\IntegrationConfigService::class)->hydrateRuntimeConfig();

        return redirect()
            ->route('settings.global', ['tab' => $request->input('_tab', 'security')])
            ->with('success', 'Global settings saved successfully.');
    }

    public function updateAgencyIdentity(UpdateAgencyIdentityRequest $request)
    {
        $this->authorize('managePlatformUsers', \App\Models\User::class);

        $this->agencyIdentity->updatePrimary($request->validated());

        return redirect()
            ->route('settings.global', ['tab' => 'agency'])
            ->with('success', 'Agency profile saved.');
    }

    public function updateCredentialVault(UpdateCredentialVaultRequest $request)
    {
        $this->authorize('managePlatformUsers', \App\Models\User::class);

        $savedKeys = [];

        foreach ($request->validated('credentials', []) as $entry) {
            $payload = [
                'username' => $entry['username'] ?? null,
                'password' => $entry['password'] ?? null,
                'api_key' => $entry['api_key'] ?? null,
            ];

            if (isset($entry['metadata']) && is_array($entry['metadata'])) {
                $payload['metadata'] = $entry['metadata'];
            }

            $this->credentialVault->upsert($entry['key'], $payload);
            $savedKeys[] = $entry['key'];
        }

        app(\App\Services\IntegrationConfigService::class)->hydrateRuntimeConfig();

        $labels = IntegrationCredential::supportedKeys();
        $key = count($savedKeys) === 1 ? ($savedKeys[0] ?? null) : null;
        $label = $key ? ($labels[$key] ?? 'Integration') : 'Credential vault';

        $redirectParams = ['tab' => 'credential-vault'];
        if ($key) {
            $redirectParams['integration'] = $key;
        }

        $message = count($savedKeys) === 1
            ? "{$label} credentials saved."
            : 'Credential vault updated.';

        return redirect()
            ->route('settings.global', $redirectParams)
            ->with('success', $message);
    }

    public function testIntegration(TestGlobalIntegrationRequest $request): JsonResponse
    {
        $this->authorize('managePlatformUsers', \App\Models\User::class);

        $result = $this->integrationTests->test(
            $request->validated('slug'),
            $request->user(),
            $this->draftCredentialsForTest($request),
        );

        return response()->json($result);
    }

    /**
     * @return array{username: string, password: string, api_key: string, metadata: array<string, mixed>}|null
     */
    protected function draftCredentialsForTest(TestGlobalIntegrationRequest $request): ?array
    {
        $slug = $request->validated('slug');
        $draft = $request->validated('draft');

        if (! is_array($draft) || ! array_key_exists($slug, IntegrationCredential::supportedKeys())) {
            return null;
        }

        $draftService = app(CredentialVaultDraftService::class);
        $normalized = $draftService->normalize($draft);

        if ($normalized === null || ! $draftService->hasContent($normalized)) {
            return null;
        }

        return $normalized;
    }
}
