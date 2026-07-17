<?php

namespace App\Services\Payroll;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class AccountantsWorldClient
{
    public const AUTH_MODE_API_KEY = 'api_key';

    public const AUTH_MODE_OAUTH = 'oauth';

    public const AUTH_MODE_BOTH = 'both';

    public function baseUrl(): string
    {
        return rtrim((string) config('payroll.accountants_world_api_url', config('payroll.accountants_world_url')), '/');
    }

    public function appId(): string
    {
        return (string) config('payroll.accountants_world_app_id', '');
    }

    public function apiKey(): ?string
    {
        $key = config('payroll.accountants_world_api_key');

        if ($key) {
            return (string) $key;
        }

        $appId = $this->appId();

        return $appId !== '' ? $appId : null;
    }

    public function oauthClientId(): string
    {
        $configured = config('payroll.accountants_world_oauth_client_id');

        if (filled($configured)) {
            return (string) $configured;
        }

        if ($this->authMode() === self::AUTH_MODE_BOTH && $this->appId() !== '') {
            return $this->appId();
        }

        return '';
    }

    public function oauthClientSecret(): ?string
    {
        $secret = config('payroll.accountants_world_oauth_client_secret');

        return filled($secret) ? (string) $secret : null;
    }

    public function authMode(): string
    {
        $configured = config('payroll.accountants_world_auth_mode');

        if (in_array($configured, [self::AUTH_MODE_API_KEY, self::AUTH_MODE_OAUTH, self::AUTH_MODE_BOTH], true)) {
            return (string) $configured;
        }

        $hasApiKey = $this->appId() !== '';
        $hasOAuth = $this->oauthClientSecret() !== null;

        if ($hasApiKey && $hasOAuth) {
            return self::AUTH_MODE_BOTH;
        }

        if ($hasOAuth) {
            return self::AUTH_MODE_OAUTH;
        }

        return self::AUTH_MODE_API_KEY;
    }

    public function authModeLabel(): string
    {
        return match ($this->authMode()) {
            self::AUTH_MODE_OAUTH => 'OAuth Bearer token',
            self::AUTH_MODE_BOTH => 'API key + OAuth',
            default => 'API key (X-API-Key)',
        };
    }

    public function usesOAuth(): bool
    {
        return $this->usesOAuthAuth();
    }

    public function usesOAuthAuth(): bool
    {
        if (! in_array($this->authMode(), [self::AUTH_MODE_OAUTH, self::AUTH_MODE_BOTH], true)) {
            return false;
        }

        return $this->oauthClientSecret() !== null;
    }

    public function usesApiKeyAuth(): bool
    {
        if (! in_array($this->authMode(), [self::AUTH_MODE_API_KEY, self::AUTH_MODE_BOTH], true)) {
            return false;
        }

        return $this->apiKey() !== null;
    }

    /**
     * @return array{valid: bool, message: string}
     */
    public function validateAuthConfiguration(): array
    {
        if (! filled($this->baseUrl())) {
            return ['valid' => false, 'message' => 'Integration API URL is required.'];
        }

        return match ($this->authMode()) {
            self::AUTH_MODE_OAUTH => $this->oauthClientSecret() === null || $this->oauthClientId() === ''
                ? ['valid' => false, 'message' => 'OAuth mode requires client ID and client secret.']
                : ['valid' => true, 'message' => 'OAuth credentials configured.'],
            self::AUTH_MODE_BOTH => ($this->appId() === '' || $this->oauthClientSecret() === null || $this->oauthClientId() === '')
                ? ['valid' => false, 'message' => 'Both mode requires App ID (x-api-key) plus OAuth client ID and secret.']
                : ['valid' => true, 'message' => 'API key and OAuth credentials configured.'],
            default => $this->appId() === ''
                ? ['valid' => false, 'message' => 'API key mode requires App ID (x-api-key).']
                : ['valid' => true, 'message' => 'API key configured.'],
        };
    }

    public function oauthTokenUrl(): string
    {
        return (string) config(
            'payroll.accountants_world_oauth_token_url',
            'https://dev-auth.accountantsoffice.com/connect/token'
        );
    }

    public function oauthScope(): string
    {
        return (string) config('payroll.accountants_world_oauth_scope', 'payroll_api');
    }

    public function accessToken(): string
    {
        $cacheKey = 'accountantsworld.oauth.'.md5($this->oauthClientId().'|'.$this->oauthScope());
        $ttlSeconds = (int) config('payroll.accountants_world_token_cache_seconds', 3000);

        return Cache::remember($cacheKey, now()->addSeconds($ttlSeconds), function (): string {
            $response = Http::asForm()
                ->timeout((int) config('payroll.accountants_world_timeout', 30))
                ->post($this->oauthTokenUrl(), [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->oauthClientId(),
                    'client_secret' => $this->oauthClientSecret(),
                    'scope' => $this->oauthScope(),
                ]);

            if (! $response->successful()) {
                $body = $response->json() ?? [];
                $message = $body['error_description'] ?? $body['error'] ?? $response->body();

                Log::channel('stack')->error('AccountantsWorld OAuth token request failed', [
                    'status' => $response->status(),
                    'body' => is_array($body) ? $body : ['message' => (string) $message],
                ]);

                throw new RuntimeException('AccountantsWorld OAuth token request failed: '.(string) $message);
            }

            $token = $response->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('AccountantsWorld OAuth token response did not include access_token.');
            }

            Log::channel('stack')->info('AccountantsWorld OAuth token obtained', [
                'expires_in' => $response->json('expires_in'),
            ]);

            return $token;
        });
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        $validation = $this->validateAuthConfiguration();

        if (! $validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
            ];
        }

        try {
            if ($this->usesOAuthAuth()) {
                $this->accessToken();
            }
        } catch (RuntimeException $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }

        $schedules = $this->getPaySchedules();

        if ($schedules['success']) {
            return [
                'success' => true,
                'message' => 'Payroll API authenticated successfully using '.$this->authModeLabel().'.',
            ];
        }

        $status = $schedules['raw']['http_status'] ?? 'unknown';
        $message = $schedules['raw']['message'] ?? 'Payroll API request failed.';

        if ((int) $status === 401) {
            $message = $this->authFailureHint();
        }

        return [
            'success' => false,
            'message' => "Payroll API authentication failed (HTTP {$status}): {$message}",
        ];
    }

    protected function authFailureHint(): string
    {
        return match ($this->authMode()) {
            self::AUTH_MODE_OAUTH => 'OAuth Bearer token was rejected. Verify client ID, client secret, token URL, and payroll_api scope with AccountantsWorld.',
            self::AUTH_MODE_BOTH => 'API key and OAuth token were sent but rejected. Verify App ID, OAuth client ID/secret, and token URL with AccountantsWorld.',
            default => 'The x-api-key (App ID) was rejected. Verify the App ID and API URL with AccountantsWorld, or switch auth mode to OAuth if your tenant uses client credentials instead.',
        };
    }

    /**
     * POST /client/employee/import
     *
     * @return array{success: bool, employee_id: ?string, raw: array}
     */
    public function createEmployee(array $payload): array
    {
        $importPayload = $this->mapEmployeeImportPayload($payload);
        $response = $this->request()->post('/client/employee/import', [$importPayload]);

        return $this->parseEmployeeImportResponse($response, $payload);
    }

    /**
     * GET /client/employee/info/{id}
     *
     * @return array{success: bool, employee_id: ?string, raw: array}
     */
    public function getEmployee(string $employeeId): array
    {
        $response = $this->request()->get('/client/employee/info/'.urlencode($employeeId));

        if ($response->successful()) {
            return [
                'success' => true,
                'employee_id' => (string) $employeeId,
                'raw' => is_array($response->json()) ? $response->json() : ['body' => (string) $response->body()],
            ];
        }

        return $this->parseEmployeeLookupResponse($response, 'AccountantsWorld employee lookup by ID failed');
    }

    /**
     * GET /client/employee/list — match by SSN locally.
     *
     * @return array{success: bool, employee_id: ?string, raw: array}
     */
    public function lookupEmployeeBySsn(string $ssn): array
    {
        $response = $this->request()->get('/client/employee/list');

        return $this->parseEmployeeListSsnMatch($response, $ssn);
    }

    /**
     * @return array{success: bool, batch_id: ?string, raw: array}
     *
     * @deprecated Use updatePayrollData() via AccountantsWorldPayrollSyncService.
     */
    public function exportBatch(array $payload): array
    {
        $response = $this->request()->post('/payroll/UpdatePayrollData', $payload);

        return $this->parseBatchResponse($response);
    }

    /**
     * GET /payroll/PaySchedules
     *
     * @return array{success: bool, data: mixed, raw: array<string, mixed>}
     */
    public function getPaySchedules(): array
    {
        $response = $this->request()->get('/payroll/PaySchedules');

        return $this->parseJsonResponse($response, 'AccountantsWorld pay schedules lookup failed');
    }

    /**
     * GET /payroll/GetNextPayrollData/{payScheduleId}
     *
     * @return array{success: bool, data: mixed, raw: array<string, mixed>}
     */
    public function getNextPayrollData(int $payScheduleId): array
    {
        $response = $this->request()->get('/payroll/GetNextPayrollData/'.$payScheduleId);

        return $this->parseJsonResponse($response, 'AccountantsWorld next payroll data lookup failed');
    }

    /**
     * POST /payroll/UpdatePayrollData
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, data: mixed, raw: array<string, mixed>}
     */
    public function updatePayrollData(array $payload): array
    {
        $response = $this->request()->post('/payroll/UpdatePayrollData', $payload);

        return $this->parsePayrollUpdateResponse($response);
    }

    /**
     * GET /payroll/PayrollDetails/{startDate}/{endDate}
     *
     * @return array{success: bool, data: mixed, raw: array<string, mixed>}
     */
    public function getPayrollDetails(string $startDate, ?string $endDate = null): array
    {
        $start = $this->formatPathDateTime($startDate);
        $end = $this->formatPathDateTime($endDate ?? now()->toIso8601String());
        $response = $this->request()->get('/payroll/PayrollDetails/'.$start.'/'.$end);

        return $this->parseJsonResponse($response, 'AccountantsWorld payroll details lookup failed');
    }

    /**
     * GET /payroll/PayrollPayStubs/{payrollId}
     *
     * @return array{success: bool, data: mixed, raw: array<string, mixed>}
     */
    public function getPayrollPayStubs(int $payrollId): array
    {
        $response = $this->request()->get('/payroll/PayrollPayStubs/'.$payrollId);

        return $this->parseJsonResponse($response, 'AccountantsWorld payroll pay stubs lookup failed');
    }

    protected function formatPathDateTime(string $value): string
    {
        return rawurlencode($value);
    }

    /**
     * @return array{success: bool, data: mixed, raw: array<string, mixed>}
     */
    protected function parseJsonResponse(Response $response, string $logContext): array
    {
        $body = $response->json() ?? [];

        if ($response->successful()) {
            return [
                'success' => true,
                'data' => $body,
                'raw' => is_array($body) ? $body : ['body' => (string) $response->body()],
            ];
        }

        Log::channel('stack')->warning($logContext, [
            'status' => $response->status(),
            'body' => $body,
        ]);

        return [
            'success' => false,
            'data' => null,
            'raw' => $this->failureRaw($response),
        ];
    }

    /**
     * @return array{success: bool, data: mixed, raw: array<string, mixed>}
     */
    protected function parsePayrollUpdateResponse(Response $response): array
    {
        $body = $response->json() ?? [];

        if ($response->successful()) {
            $success = (bool) ($body['success'] ?? true);

            if (! $success) {
                Log::channel('stack')->warning('AccountantsWorld payroll update rejected', [
                    'status' => $response->status(),
                    'body' => $body,
                ]);
            } else {
                Log::channel('stack')->info('AccountantsWorld payroll data updated', [
                    'messages' => $body['messages'] ?? [],
                ]);
            }

            return [
                'success' => $success,
                'data' => $body,
                'raw' => is_array($body) ? $body : ['body' => (string) $response->body()],
            ];
        }

        Log::channel('stack')->warning('AccountantsWorld payroll update failed', [
            'status' => $response->status(),
            'body' => $body,
        ]);

        return [
            'success' => false,
            'data' => null,
            'raw' => $this->failureRaw($response),
        ];
    }

    protected function request(): PendingRequest
    {
        $validation = $this->validateAuthConfiguration();

        if (! $validation['valid']) {
            throw new RuntimeException($validation['message']);
        }

        $request = Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('payroll.accountants_world_timeout', 30));

        if ($this->usesOAuthAuth()) {
            $request = $request->withToken($this->accessToken());
        }

        if ($this->usesApiKeyAuth() && ($apiKey = $this->apiKey())) {
            $request = $request->withHeaders(['X-API-Key' => $apiKey]);
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function mapEmployeeImportPayload(array $payload): array
    {
        $isSalary = ($payload['payType'] ?? 'hourly') === 'salary';
        $rate = round((float) ($payload['payRate'] ?? 0), 2);

        $import = [
            'firstName' => $payload['firstName'] ?? null,
            'lastName' => $payload['lastName'] ?? null,
            'ssn' => $this->normalizeSsn((string) ($payload['ssn'] ?? '')),
            'active' => true,
            'salaried' => $isSalary,
            'jobTitle' => $payload['department'] ?? 'Caregiver',
        ];

        if ($payScheduleId = config('payroll.accountants_world_pay_schedule_id')) {
            $import['paySchedule'] = (int) $payScheduleId;
        }

        if ($isSalary) {
            $import['salary'] = $rate;
        } else {
            $import['payTypes'] = [[
                'rate' => $rate,
                'hours' => 0,
            ]];
        }

        return array_filter(
            $import,
            fn ($value) => $value !== null && $value !== ''
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, employee_id: ?string, raw: array}
     */
    protected function parseEmployeeImportResponse(Response $response, array $payload): array
    {
        $body = $response->json() ?? [];

        if (! $response->successful()) {
            Log::channel('stack')->warning('AccountantsWorld employee import failed', [
                'status' => $response->status(),
                'body' => $body,
            ]);

            return [
                'success' => false,
                'employee_id' => null,
                'raw' => $this->failureRaw($response),
            ];
        }

        $status = $this->normalizeImportStatusBody($body);

        if (($status['numberFailed'] ?? 0) > 0 || ($status['success'] ?? true) === false) {
            $reason = collect($status['reason'] ?? [])
                ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                ->implode(' ');

            Log::channel('stack')->warning('AccountantsWorld employee import rejected', [
                'status' => $response->status(),
                'body' => $body,
            ]);

            return [
                'success' => false,
                'employee_id' => null,
                'raw' => array_merge($this->failureRaw($response), [
                    'message' => $reason !== '' ? $reason : 'AccountantsWorld rejected the employee import.',
                ]),
            ];
        }

        $employeeId = $this->extractEmployeeIdFromImportStatus($status);
        $imported = (int) ($status['numberImported'] ?? 0);
        $updated = (int) ($status['numberUpdated'] ?? 0);

        if (! $employeeId && $imported === 0 && $updated === 0) {
            Log::channel('stack')->warning('AccountantsWorld employee import returned no changes', [
                'status' => $response->status(),
                'body' => $body,
            ]);

            return [
                'success' => false,
                'employee_id' => null,
                'raw' => array_merge($this->failureRaw($response), [
                    'message' => 'AccountantsWorld did not import or update any employee records.',
                ]),
            ];
        }

        if (! $employeeId && ! empty($payload['ssn'])) {
            $lookup = $this->lookupEmployeeBySsn((string) $payload['ssn']);
            $employeeId = $lookup['employee_id'] ?? null;
        }

        if (! $employeeId) {
            return [
                'success' => false,
                'employee_id' => null,
                'raw' => array_merge($this->failureRaw($response), [
                    'message' => 'Employee import succeeded but AccountantsWorld did not return an employee ID.',
                ]),
            ];
        }

        $result = [
            'success' => true,
            'employee_id' => $employeeId,
            'raw' => is_array($body) ? $body : ['body' => (string) $response->body()],
        ];

        Log::channel('stack')->info('AccountantsWorld employee imported', $result);

        return $result;
    }

    /**
     * @return array{success: bool, employee_id: ?string, raw: array}
     */
    protected function parseEmployeeListSsnMatch(Response $response, string $ssn): array
    {
        $body = $response->json() ?? [];

        if (! $response->successful()) {
            return $this->parseEmployeeLookupResponse($response, 'AccountantsWorld employee lookup by SSN failed');
        }

        $targetSsn = $this->normalizeSsn($ssn);

        foreach ($body['employeeList'] ?? [] as $employee) {
            if (! is_array($employee)) {
                continue;
            }

            if ($this->normalizeSsn((string) ($employee['ssn'] ?? '')) !== $targetSsn) {
                continue;
            }

            $employeeId = $employee['employeeId'] ?? $employee['employee_id'] ?? null;

            if (is_numeric($employeeId) || (is_string($employeeId) && $employeeId !== '')) {
                return [
                    'success' => true,
                    'employee_id' => (string) $employeeId,
                    'raw' => is_array($body) ? $body : ['body' => (string) $response->body()],
                ];
            }
        }

        return [
            'success' => false,
            'employee_id' => null,
            'raw' => array_merge($this->failureRaw($response), [
                'message' => 'No matching employee was returned by AccountantsWorld.',
            ]),
        ];
    }

    /**
     * @param  mixed  $body
     * @return array<string, mixed>
     */
    protected function normalizeImportStatusBody(mixed $body): array
    {
        if (is_array($body) && array_is_list($body) && isset($body[0]) && is_array($body[0])) {
            return $body[0];
        }

        return is_array($body) ? $body : [];
    }

    /**
     * @param  array<string, mixed>  $status
     */
    protected function extractEmployeeIdFromImportStatus(array $status): ?string
    {
        $direct = $status['employeeId'] ?? $status['employee_id'] ?? null;

        if (is_numeric($direct) || (is_string($direct) && $direct !== '')) {
            return (string) $direct;
        }

        foreach ($status['employeesModified'] ?? [] as $employee) {
            if (! is_array($employee)) {
                continue;
            }

            $nested = $employee['employeeId'] ?? $employee['employee_id'] ?? $employee['id'] ?? null;

            if (is_numeric($nested) || (is_string($nested) && $nested !== '')) {
                return (string) $nested;
            }
        }

        return null;
    }

    protected function normalizeSsn(string $ssn): string
    {
        return preg_replace('/\D+/', '', $ssn) ?? '';
    }

    /**
     * @return array{success: bool, employee_id: ?string, raw: array}
     */
    protected function parseEmployeeResponse(Response $response): array
    {
        $body = $response->json() ?? [];

        if ($response->successful()) {
            $result = [
                'success' => true,
                'employee_id' => $body['employeeId'] ?? $body['employee_id'] ?? $body['id'] ?? null,
                'raw' => is_array($body) ? $body : ['body' => (string) $response->body()],
            ];

            Log::channel('stack')->info('AccountantsWorld employee created', $result);

            return $result;
        }

        Log::channel('stack')->warning('AccountantsWorld employee creation failed', [
            'status' => $response->status(),
            'body' => $body,
        ]);

        return [
            'success' => false,
            'employee_id' => null,
            'raw' => $this->failureRaw($response),
        ];
    }

    /**
     * @return array{success: bool, employee_id: ?string, raw: array}
     */
    protected function parseEmployeeLookupResponse(Response $response, string $logContext): array
    {
        $body = $response->json() ?? [];

        if ($response->successful()) {
            $employeeId = $this->extractEmployeeIdFromBody($body);

            if ($employeeId) {
                $result = [
                    'success' => true,
                    'employee_id' => $employeeId,
                    'raw' => is_array($body) ? $body : ['body' => (string) $response->body()],
                ];

                Log::channel('stack')->info('AccountantsWorld employee verified', $result);

                return $result;
            }

            Log::channel('stack')->warning($logContext.' — employee not found in response', [
                'status' => $response->status(),
                'body' => $body,
            ]);

            return [
                'success' => false,
                'employee_id' => null,
                'raw' => array_merge($this->failureRaw($response), [
                    'message' => 'No matching employee was returned by AccountantsWorld.',
                ]),
            ];
        }

        Log::channel('stack')->warning($logContext, [
            'status' => $response->status(),
            'body' => $body,
        ]);

        return [
            'success' => false,
            'employee_id' => null,
            'raw' => $this->failureRaw($response),
        ];
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $body
     */
    protected function extractEmployeeIdFromBody(array $body): ?string
    {
        $direct = $body['employeeId'] ?? $body['employee_id'] ?? $body['id'] ?? null;

        if (is_string($direct) && $direct !== '') {
            return $direct;
        }

        if (is_numeric($direct)) {
            return (string) $direct;
        }

        foreach (['employees', 'employeeList', 'data', 'results'] as $listKey) {
            if (! isset($body[$listKey]) || ! is_array($body[$listKey])) {
                continue;
            }

            $first = $body[$listKey][0] ?? null;

            if (is_array($first)) {
                $nested = $first['employeeId'] ?? $first['employee_id'] ?? $first['id'] ?? null;

                if (is_string($nested) && $nested !== '') {
                    return $nested;
                }

                if (is_numeric($nested)) {
                    return (string) $nested;
                }
            }
        }

        if ($body !== [] && array_is_list($body) && is_array($body[0] ?? null)) {
            $first = $body[0];
            $nested = $first['employeeId'] ?? $first['employee_id'] ?? $first['id'] ?? null;

            if (is_string($nested) && $nested !== '') {
                return $nested;
            }

            if (is_numeric($nested)) {
                return (string) $nested;
            }
        }

        return null;
    }

    /**
     * @return array{success: bool, batch_id: ?string, raw: array}
     */
    protected function parseBatchResponse(Response $response): array
    {
        $body = $response->json() ?? [];

        if ($response->successful()) {
            return [
                'success' => true,
                'batch_id' => $body['batchId'] ?? $body['batch_id'] ?? $body['id'] ?? null,
                'raw' => is_array($body) ? $body : ['body' => (string) $response->body()],
            ];
        }

        return [
            'success' => false,
            'batch_id' => null,
            'raw' => $this->failureRaw($response),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function failureRaw(Response $response): array
    {
        $body = $response->json();
        $raw = is_array($body) && $body !== [] ? $body : [];
        $bodyText = $this->extractBodyText($response);

        $raw['http_status'] = $response->status();

        if ($bodyText !== '') {
            $raw['body_text'] = $bodyText;

            if (empty($raw['message'])) {
                $raw['message'] = $bodyText;
            }
        }

        return $raw;
    }

    protected function extractBodyText(Response $response): string
    {
        $body = (string) $response->body();

        if ($body === '') {
            return '';
        }

        $json = json_decode($body, true);

        if (is_array($json)) {
            foreach (['message', 'error', 'detail'] as $key) {
                if (! empty($json[$key]) && is_string($json[$key])) {
                    return trim(strip_tags($json[$key]));
                }
            }
        }

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches)) {
            return trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $body, $matches)) {
            return trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($body)) ?? '');

        return Str::limit($plain, 240);
    }
}
