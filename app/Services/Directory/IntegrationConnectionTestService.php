<?php

namespace App\Services\Directory;

use App\Models\Contact;
use App\Models\GlobalIntegrationHealth;
use App\Models\IntegrationConnectionHealth;
use App\Models\IntegrationCredential;
use App\Services\Availity\AvailityClient;
use App\Services\CredentialVaultDraftService;
use App\Services\CredentialVaultService;
use App\Services\GlobalSettingsService;
use App\Services\Directory\Concerns\RunsIntegrationHttpChecks;
use App\Services\HHA\HHAExchangeClient;
use App\Services\Integrations\DocuSignClient;
use App\Support\IntegrationTestResult;
use Illuminate\Support\Carbon;

class IntegrationConnectionTestService
{
    use RunsIntegrationHttpChecks;

    public function __construct(
        protected CredentialVaultService $vault,
        protected HHAExchangeClient $hhaClient,
        protected AvailityClient $availityClient,
        protected DocuSignClient $docuSignClient,
    ) {}

    /**
     * @return array{success: bool, status: string, message: string, health: IntegrationConnectionHealth}
     */
    public function testContact(Contact $contact): array
    {
        $credentialKey = $contact->resolvedCredentialKey();

        if (! $credentialKey) {
            $health = $this->persistHealth($contact, [
                'status' => IntegrationConnectionHealth::STATUS_NOT_CONFIGURED,
                'message' => 'Link this card to a credential in Global Settings → Credential Vault.',
            ]);

            return [
                'success' => false,
                'status' => IntegrationConnectionHealth::STATUS_NOT_CONFIGURED,
                'message' => $health->message,
                'health' => $health,
            ];
        }

        $result = $this->testCredentialKey($credentialKey)->toArray();
        $previous = $contact->connectionHealth;
        $errors30d = $previous?->errors_30d ?? 0;

        if (! $result['success']) {
            $errors30d = $this->incrementErrors($previous);
        } elseif ($previous && $previous->last_tested_at?->lt(now()->subDays(30))) {
            $errors30d = 0;
        }

        $health = $this->persistHealth($contact, [
            'status' => $result['success']
                ? IntegrationConnectionHealth::STATUS_CONNECTED
                : IntegrationConnectionHealth::STATUS_ERROR,
            'message' => $result['summary'] ?? $result['message'],
            'errors_30d' => $errors30d,
            'last_sync_at' => $result['success'] ? now() : $previous?->last_sync_at,
        ]);

        return array_merge($result, ['health' => $health]);
    }

    public function testCredentialKey(string $key, ?array $draft = null): IntegrationTestResult
    {
        $draftService = app(CredentialVaultDraftService::class);
        $normalized = $draftService->normalize($draft);
        $useDraft = $normalized !== null && $draftService->hasContent($normalized);
        $restore = $useDraft ? $draftService->apply($key, $normalized) : null;

        try {
            return match ($key) {
                IntegrationCredential::KEY_HHA => $this->testHha(),
                IntegrationCredential::KEY_ACCOUNTANTSWORLD => $this->testAccountantsWorld(),
                IntegrationCredential::KEY_AVAILITY => $this->testAvaility(),
                IntegrationCredential::KEY_RINGCENTRAL => $this->testRingCentral(),
                IntegrationCredential::KEY_GOOGLE_WORKSPACE => $this->testGoogleWorkspace(),
                IntegrationCredential::KEY_DOCUSIGN => $this->testDocuSign(),
                IntegrationCredential::KEY_CHAMPS,
                IntegrationCredential::KEY_MDHHS,
                IntegrationCredential::KEY_SIGMA,
                IntegrationCredential::KEY_ICHAT => $this->testPortalCredential($key, $useDraft ? $normalized : null),
                default => IntegrationTestResult::make(
                    false,
                    GlobalIntegrationHealth::STATUS_NOT_CONFIGURED,
                    'No connection test is defined for this integration.',
                ),
            };
        } finally {
            if ($restore !== null) {
                $restore();
            }
        }
    }

    public function testStatePortals(): IntegrationTestResult
    {
        $keys = array_keys(config('global_settings.vault_rpa', []));
        $result = new IntegrationTestResult(method: 'rpa');
        $failures = [];

        foreach ($keys as $key) {
            $portal = $this->testPortalCredential($key);
            $label = IntegrationCredential::supportedKeys()[$key] ?? $key;

            foreach ($portal->checks() as $check) {
                $result->check($label.' — '.$check['name'], $check['passed'], $check['detail'], $check['duration_ms'] ?? null);
            }

            if (! $portal->success) {
                $failures[] = $label;
            }
        }

        $passed = $result->passedChecks();
        $total = $result->totalChecks();

        if ($passed === 0) {
            return $result
                ->notConfigured('No state portal credentials are configured in the vault.')
                ->recommend('Open Credential Vault and add CHAMPS, Sigma, ICHAT, and MDHHS logins.');
        }

        if ($failures !== []) {
            return $result
                ->partial($passed.'/'.$total.' portal checks passed. Review: '.implode(', ', $failures).'.')
                ->recommend('Complete missing portal credentials in Credential Vault.');
        }

        return $result->passed('All state portal credentials verified ('.$passed.'/'.$total.' checks passed).');
    }

    public function testSamOig(): IntegrationTestResult
    {
        $result = new IntegrationTestResult(method: 'api_download');

        foreach (config('global_settings.exclusion_endpoints', []) as $endpoint) {
            $probe = $this->probeUrl(
                (string) $endpoint['url'],
                15,
                (string) ($endpoint['method'] ?? 'head'),
            );

            $result->check(
                (string) $endpoint['name'],
                $probe['reachable'],
                $probe['detail'],
                $probe['duration_ms'],
            );
        }

        if ($result->passedChecks() < $result->totalChecks()) {
            return $result
                ->failed(GlobalIntegrationHealth::STATUS_ERROR, 'One or more exclusion-list endpoints are unreachable.')
                ->recommend('Verify outbound HTTPS access from the application server.');
        }

        return $result->passed('SAM.gov and OIG LEIE endpoints are reachable for monthly exclusion batches.');
    }

    public function testBillingClaims(): IntegrationTestResult
    {
        $result = new IntegrationTestResult(method: 'api');
        $failures = [];

        $channelTests = [
            'MICH 837P (Availity)' => $this->testAvaility(),
            'DHS email (Google Workspace)' => $this->testGoogleWorkspace(),
            'Sigma portal (RPA)' => $this->testPortalCredential(IntegrationCredential::KEY_SIGMA),
        ];

        foreach ($channelTests as $label => $channel) {
            foreach ($channel->checks() as $check) {
                $result->check($label.' — '.$check['name'], $check['passed'], $check['detail'], $check['duration_ms'] ?? null);
            }

            if (! $channel->success) {
                $failures[] = $label;
            }
        }

        $aswEmail = config('billing_claims_audit.default_asw_email');

        if (! filled($aswEmail)) {
            $aswEmail = app(GlobalSettingsService::class)->get('billing.default_asw_email');
        }

        $hasAsw = filled($aswEmail);
        $result->check(
            'Default ASW email',
            $hasAsw,
            $hasAsw
                ? 'Fallback ASW recipient: '.$aswEmail
                : 'Set default ASW email in Billing & Claims settings or Sigma vault metadata',
        );

        if (! $hasAsw) {
            $failures[] = 'Default ASW email';
        }

        $passed = $result->passedChecks();
        $total = $result->totalChecks();

        if ($passed === 0) {
            return $result
                ->notConfigured('Billing & Claims credentials are not configured.')
                ->recommend('Open Global Settings → Billing & Claims and Credential Vault to add Availity, Google Workspace, Sigma, and ASW email.');
        }

        if ($failures !== []) {
            return $result
                ->partial($passed.'/'.$total.' billing checks passed. Review: '.implode(', ', $failures).'.')
                ->recommend('Complete missing billing credentials in Billing & Claims settings and Credential Vault.');
        }

        return $result->passed('All billing & claims channel credentials verified ('.$passed.'/'.$total.' checks passed).');
    }

    public function testUhcEdi(): IntegrationTestResult
    {
        $result = new IntegrationTestResult(method: 'edi');
        $host = config('billing_claims_audit.uhc_edi_host', env('UHC_EDI_HOST'));

        if (! $host) {
            return $result
                ->check('EDI host configuration', false, 'UHC_EDI_HOST is not set')
                ->notConfigured('UHC EDI is not configured yet.')
                ->recommend('Set UHC_EDI_HOST in the environment when the payer EDI connection is provisioned.');
        }

        $result->check('EDI host configuration', true, 'Host configured: '.$host);

        $probe = $this->probeUrl(rtrim((string) $host, '/'), 15, 'head');
        $result->check('EDI endpoint reachability', $probe['reachable'], $probe['detail'], $probe['duration_ms']);

        if (! $probe['reachable']) {
            return $result
                ->failed(GlobalIntegrationHealth::STATUS_ERROR, 'UHC EDI endpoint is unreachable.')
                ->recommend('Confirm the EDI host URL and firewall rules with the payer.');
        }

        return $result->passed('UHC EDI endpoint is reachable.');
    }

    public function testRetell(): IntegrationTestResult
    {
        $result = new IntegrationTestResult(method: 'api');
        $client = app(\App\Services\Integrations\RetellClient::class);

        $result->check('API key + agent ID', $client->isConfigured(), $client->isConfigured()
            ? 'Retell API key and wellness agent configured'
            : 'RETELL_API_KEY and RETELL_AGENT_ID are required');

        if (! $client->isConfigured()) {
            return $result
                ->notConfigured('Retell is not configured.')
                ->recommend('Set RETELL_API_KEY, RETELL_AGENT_ID and RETELL_FROM_NUMBER to enable the monthly wellness calls.');
        }

        $connection = $client->testConnection();
        $result->check('Retell API', (bool) $connection['success'], (string) $connection['message']);

        if (! $connection['success']) {
            return $result
                ->failed(GlobalIntegrationHealth::STATUS_ERROR, (string) $connection['message'])
                ->recommend('Verify the Retell API key at dashboard.retellai.com.');
        }

        return $result->passed((string) $connection['message']);
    }

    public function isCredentialConfigured(string $key): bool
    {
        return $this->testCredentialKey($key)->success;
    }

    /**
     * @param  array{status: string, message?: ?string, last_sync_at?: ?Carbon, last_batch_at?: ?Carbon, errors_30d?: int}  $data
     */
    protected function persistHealth(Contact $contact, array $data): IntegrationConnectionHealth
    {
        $health = IntegrationConnectionHealth::query()->firstOrNew(['contact_id' => $contact->id]);
        $health->fill([
            'status' => $data['status'],
            'message' => $data['message'] ?? null,
            'last_sync_at' => $data['last_sync_at'] ?? $health->last_sync_at,
            'last_batch_at' => $data['last_batch_at'] ?? $health->last_batch_at,
            'errors_30d' => $data['errors_30d'] ?? $health->errors_30d ?? 0,
            'last_tested_at' => now(),
        ]);
        $health->save();

        return $health;
    }

    protected function incrementErrors(?IntegrationConnectionHealth $previous): int
    {
        $count = $previous?->errors_30d ?? 0;

        if ($previous?->last_tested_at && $previous->last_tested_at->lt(now()->subDays(30))) {
            return 1;
        }

        return $count + 1;
    }

    protected function testHha(): IntegrationTestResult
    {
        $status = $this->hhaClient->getConnectionStatus();
        $result = new IntegrationTestResult(method: 'api');

        foreach ($status['checks'] ?? [] as $check) {
            $result->check(
                (string) $check['name'],
                (bool) $check['passed'],
                (string) $check['detail'],
                $check['duration_ms'] ?? null,
            );
        }

        // Test 1-001 / Swagger Authorize: OAuth success is enough for the vault test.
        if (($status['oauth_ok'] ?? false) === true || ($status['connected'] ?? false) === true) {
            if (($status['status'] ?? '') === 'pending_attestation') {
                return $result
                    ->passed((string) $status['message'])
                    ->recommend('OAuth works (same as Swagger Authorize). Set Attestation status to Approved when ready for live EVV sync.');
            }

            return $result->passed((string) $status['message']);
        }

        $normalized = match ($status['status']) {
            'missing_credentials', 'pending_attestation', 'not_configured' => GlobalIntegrationHealth::STATUS_NOT_CONFIGURED,
            default => GlobalIntegrationHealth::STATUS_ERROR,
        };

        return $result
            ->failed($normalized, (string) $status['message'])
            ->recommend('Confirm Token URL is …/identity/connect/token, scope is write:aggregator, and client_id/secret match Swagger Authorize.');
    }

    protected function testAccountantsWorld(): IntegrationTestResult
    {
        $result = new IntegrationTestResult(method: 'api');
        $client = app(\App\Services\Payroll\AccountantsWorldClient::class);
        $authMode = $client->authMode();
        $validation = $client->validateAuthConfiguration();
        $apiUrl = rtrim((string) config('payroll.accountants_world_api_url', ''), '/');

        $result->check(
            'API URL',
            filled($apiUrl),
            filled($apiUrl) ? $apiUrl : 'Integration API URL is required.'
        );

        $result->check(
            'Authentication mode',
            true,
            match ($authMode) {
                \App\Services\Payroll\AccountantsWorldClient::AUTH_MODE_OAUTH => 'OAuth Bearer token only',
                \App\Services\Payroll\AccountantsWorldClient::AUTH_MODE_BOTH => 'API key + OAuth together',
                default => 'API key (X-API-Key) only',
            }
        );

        $result->check(
            'Credentials for selected mode',
            $validation['valid'],
            $validation['message']
        );

        if (! filled($apiUrl) || ! $validation['valid']) {
            return $result
                ->notConfigured('AccountantsWorld credentials are incomplete for the selected auth mode.')
                ->recommend('Open Credential Vault → AccountantsWorld, choose API key or OAuth, and fill only the fields for that mode.');
        }

        $connection = $client->testConnection();
        $result->check('Payroll API authentication', (bool) $connection['success'], (string) $connection['message']);

        if (! $connection['success']) {
            return $result
                ->failed(GlobalIntegrationHealth::STATUS_ERROR, (string) $connection['message'])
                ->recommend('Confirm credentials match the selected auth mode. Try the other mode if AccountantsWorld uses OAuth instead of x-api-key, or Both if they require both.');
        }

        return $result->passed('AccountantsWorld payroll API authenticated successfully.');
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    protected function testAvaility(): IntegrationTestResult
    {
        $result = new IntegrationTestResult(method: 'api');
        $availity = config('services.availity', []);
        $env = $availity['env'] ?? 'demo';
        $hasDemo = filled($availity['demo_key'] ?? null) && filled($availity['demo_secret'] ?? null);
        $hasProd = filled($availity['prod_key'] ?? null) && filled($availity['prod_secret'] ?? null);

        $result->check('Environment', true, ucfirst($env).' environment selected');

        if ($env === 'production') {
            $result->check('Production OAuth credentials', (bool) $hasProd, $hasProd
                ? 'Production client ID and secret stored'
                : 'Production client ID and secret are required');
        } else {
            $result->check('Demo OAuth credentials', (bool) $hasDemo, $hasDemo
                ? 'Demo client ID and secret stored'
                : 'Demo client ID and secret are required');
        }

        if (($env === 'production' && ! $hasProd) || ($env !== 'production' && ! $hasDemo)) {
            return $result
                ->notConfigured('Availity OAuth credentials are incomplete for the selected environment.')
                ->recommend('Open Credential Vault → Availity.');
        }

        $oauth = $this->availityClient->testConnection();
        $result->check('OAuth token exchange', (bool) $oauth['success'], (string) $oauth['message']);

        if (! $oauth['success']) {
            return $result
                ->failed(GlobalIntegrationHealth::STATUS_ERROR, (string) $oauth['message'])
                ->recommend('Confirm Availity client credentials and token URL for the '.$env.' environment.');
        }

        return $result->passed('Availity OAuth connected for the '.$env.' environment.');
    }

    protected function testRingCentral(): IntegrationTestResult
    {
        $result = new IntegrationTestResult(method: 'api');
        $client = app(\App\Services\Integrations\RingCentralClient::class);

        $result->check('Vault credentials', $client->isConfigured(), $client->isConfigured()
            ? 'Client ID and secret/JWT configured'
            : 'Client ID and secret (or JWT) are required');

        if (! $client->isConfigured()) {
            return $result
                ->notConfigured('RingCentral credentials are not configured.')
                ->recommend('Open Credential Vault → RingCentral.');
        }

        $connection = $client->testConnection();
        $result->check('OAuth + extension API', (bool) $connection['success'], (string) $connection['message']);

        if (! $connection['success']) {
            return $result
                ->failed(GlobalIntegrationHealth::STATUS_ERROR, (string) $connection['message'])
                ->recommend('Verify RingCentral server URL, JWT or client credentials, and extension permissions.');
        }

        $fromNumber = $client->resolveFromNumber();
        $result->check(
            'SMS sender number',
            filled($fromNumber),
            filled($fromNumber)
                ? 'Outbound SMS from '.$fromNumber
                : 'No SMS sender number found. Set caller ID in Credential Vault or assign a number to this extension.',
        );

        $smsPermission = $client->testSmsSendPermission();
        $result->check('SMS API permission', (bool) $smsPermission['success'], (string) $smsPermission['message']);

        $faxPermission = $client->testFaxSendPermission();
        $result->check('Fax API permission', (bool) $faxPermission['success'], (string) $faxPermission['message']);

        if (! filled($fromNumber)) {
            return $result
                ->partial((string) $connection['message'].' SMS sending needs an outbound number.')
                ->recommend('Add the outbound SMS / caller ID number in Credential Vault → RingCentral, or assign an SMS-capable direct number to this extension.');
        }

        return $result->passed((string) $connection['message']);
    }

    protected function testGoogleWorkspace(): IntegrationTestResult
    {
        $result = new IntegrationTestResult(method: 'api');
        $client = app(\App\Services\Integrations\GoogleWorkspaceClient::class);

        $hasRefreshToken = filled(config('google_workspace.refresh_token'));

        $result->check('Vault credentials', $client->isConfigured(), $client->isConfigured()
            ? 'Client ID, secret, and delegated user configured'
            : 'Client ID, secret, and delegated user are required');

        $result->check('Refresh token', $hasRefreshToken, $hasRefreshToken
            ? 'Refresh token present'
            : 'Refresh token is required');

        if (! $client->isConfigured() || ! $hasRefreshToken) {
            return $result
                ->notConfigured('Google Workspace credentials are incomplete.')
                ->recommend('Fill delegated email, client ID, client secret, and refresh token in Credential Vault → Google Workspace.');
        }

        $connection = $client->testConnection();
        $result->check('OAuth + Gmail profile API', (bool) $connection['success'], (string) $connection['message']);

        if (! $connection['success']) {
            return $result
                ->failed(GlobalIntegrationHealth::STATUS_ERROR, (string) $connection['message'])
                ->recommend('Confirm the refresh token and delegated mailbox access.');
        }

        return $result->passed((string) $connection['message']);
    }

    protected function testDocuSign(): IntegrationTestResult
    {
        $result = new IntegrationTestResult(method: 'api');
        $integrationKey = $this->stringOrNull(config('docusign.integration_key'));
        $accountId = $this->stringOrNull(config('docusign.account_id'));
        $hasUser = filled($accountId);
        $hasKey = filled($integrationKey);

        $result->check('Integration user', $hasUser, $hasUser ? 'Integration user ID stored' : 'Integration user ID is required');
        $result->check('API key', $hasKey, $hasKey ? 'API key stored in vault' : 'API key is required');

        if (! $hasUser || ! $hasKey) {
            return $result
                ->notConfigured('DocuSign integration user and API key are required.')
                ->recommend('Open Credential Vault → DocuSign.');
        }

        $baseUrl = (string) (config('docusign.base_url') ?? 'https://demo.docusign.net');
        $connection = $this->docuSignClient->testConnection(
            (string) $integrationKey,
            (string) $accountId,
            $baseUrl,
        );

        $result->check('DocuSign account API', (bool) $connection['success'], (string) $connection['message']);

        if (! $connection['success']) {
            return $result
                ->failed(GlobalIntegrationHealth::STATUS_ERROR, (string) $connection['message'])
                ->recommend('Verify DocuSign base URL, integration key, and impersonated user GUID.');
        }

        return $result->passed((string) $connection['message']);
    }

    /**
     * @param  array{username: string, password: string, api_key: string, metadata: array<string, mixed>}|null  $draft
     */
    protected function testPortalCredential(string $key, ?array $draft = null): IntegrationTestResult
    {
        $result = new IntegrationTestResult(method: 'rpa');
        $label = IntegrationCredential::supportedKeys()[$key] ?? $key;
        $portalMeta = config('global_settings.vault_rpa.'.$key, []);
        $credential = $this->vault->get($key);

        $username = filled($draft['username'] ?? null) ? $draft['username'] : $credential?->username;
        $password = filled($draft['password'] ?? null) ? $draft['password'] : $credential?->password;

        $hasLogin = filled($username);
        $hasSecret = filled($password);

        $result->check('Portal login', $hasLogin, $hasLogin ? 'Username stored' : 'Username is required in Credential Vault');
        $result->check('Portal password', $hasSecret, $hasSecret ? 'Password encrypted in vault' : 'Password is required in Credential Vault');

        if (! $hasLogin || ! $hasSecret) {
            return $result
                ->notConfigured($label.' credentials are incomplete.')
                ->recommend('Open Credential Vault → '.$label.'.');
        }

        $metaPortal = data_get($draft, 'metadata.portal_url') ?? $credential?->metadata['portal_url'] ?? null;
        $portalUrl = filled($metaPortal) ? (string) $metaPortal : ($portalMeta['portal_url'] ?? null);

        if ($portalUrl) {
            $probe = $this->probeUrl(
                (string) $portalUrl,
                15,
                (string) ($portalMeta['test_method'] ?? 'head'),
            );

            $result->check('Portal endpoint', $probe['reachable'], $probe['detail'], $probe['duration_ms']);

            if (! $probe['reachable']) {
                return $result
                    ->failed(GlobalIntegrationHealth::STATUS_ERROR, $label.' portal endpoint is unreachable.')
                    ->recommend('Verify portal URL and outbound network access. RPA login is not attempted during this test.');
            }
        } else {
            $result->check('Portal endpoint', true, 'Portal URL not configured — credential presence only');
        }

        return $result->passed($label.' vault credentials verified. RPA agents can retrieve secrets at runtime.');
    }

    /**
     * @return array{reachable: bool, detail: string, duration_ms: int, status_code: ?int}
     */
    protected function probePayrollApi(string $apiUrl, string $apiKey, int $timeoutSeconds): array
    {
        $url = rtrim($apiUrl, '/').'/payroll/PaySchedules';
        $started = microtime(true);

        try {
            $response = \Illuminate\Support\Facades\Http::timeout($timeoutSeconds)
                ->withHeaders([
                    'User-Agent' => 'BeydounTech-IntegrationHealth/1.0',
                    'X-API-Key' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($url);

            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $status = $response->status();
            $reachable = $response->successful() || in_array($status, [401, 403], true);

            return [
                'reachable' => $reachable,
                'detail' => $url.' — HTTP '.$status.' in '.$durationMs.'ms',
                'duration_ms' => $durationMs,
                'status_code' => $status,
            ];
        } catch (\Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);

            return [
                'reachable' => false,
                'detail' => $url.' — '.$exception->getMessage(),
                'duration_ms' => $durationMs,
                'status_code' => null,
            ];
        }
    }
}
