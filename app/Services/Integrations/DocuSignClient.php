<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DocuSignClient
{
    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(string $integrationKey, string $accountId, string $baseUrl): array
    {
        if ($integrationKey === '' || $accountId === '') {
            return [
                'success' => false,
                'message' => 'DocuSign integration user and API key are required.',
            ];
        }

        // Temporarily apply the provided credentials for the probe.
        config([
            'docusign.integration_key' => $integrationKey,
            'docusign.account_id' => $accountId,
            'docusign.base_url' => $baseUrl ?: config('docusign.base_url'),
        ]);

        $token = $this->resolveAccessToken();
        if ($token === null) {
            return [
                'success' => false,
                'message' => 'DocuSign JWT auth failed — set DOCUSIGN_USER_ID and DOCUSIGN_PRIVATE_KEY (RSA PEM) for OAuth access tokens.',
            ];
        }

        $server = rtrim($baseUrl ?: 'https://demo.docusign.net', '/');
        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->get("{$server}/restapi/v2.1/accounts/{$accountId}");

        if (! $response->successful()) {
            return [
                'success' => false,
                'message' => 'DocuSign account lookup failed: '.$this->parseError($response),
            ];
        }

        return [
            'success' => true,
            'message' => 'DocuSign connected — account '.$response->json('accountName', $accountId).' verified.',
        ];
    }

    /**
     * Best-effort envelope create. Prefer a rendered PDF document; fall back to
     * a text stub that points at the in-app signing URL. When credentials are
     * missing or the API rejects the call, returns success=false so the in-app
     * e-sign link is used as the primary path.
     *
     * @return array{success: bool, message: string, envelope_id?: string}
     */
    public function createEnvelopeForForm(
        string $formName,
        string $signerEmail,
        string $signerName,
        string $returnUrl,
        ?string $pdfBinary = null,
    ): array {
        $accountId = (string) config('docusign.account_id', '');
        $baseUrl = rtrim((string) config('docusign.base_url', 'https://demo.docusign.net'), '/');

        if ($accountId === '' || ! filter_var($signerEmail, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'DocuSign not configured or signer email missing — using secure in-app link.',
            ];
        }

        $accessToken = $this->resolveAccessToken();
        if ($accessToken === null) {
            return [
                'success' => false,
                'message' => 'DocuSign JWT not configured — using secure in-app link.',
            ];
        }

        $document = $pdfBinary !== null && $pdfBinary !== ''
            ? [
                'documentBase64' => base64_encode($pdfBinary),
                'name' => $formName.'.pdf',
                'fileExtension' => 'pdf',
                'documentId' => '1',
            ]
            : [
                'documentBase64' => base64_encode(
                    "Please sign {$formName} using the linked BeydounTech signing page:\n{$returnUrl}"
                ),
                'name' => $formName.'.txt',
                'fileExtension' => 'txt',
                'documentId' => '1',
            ];

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout((int) config('docusign.timeout', 20))
                ->post("{$baseUrl}/restapi/v2.1/accounts/{$accountId}/envelopes", [
                    'emailSubject' => 'Please sign: '.$formName,
                    'status' => 'sent',
                    'documents' => [$document],
                    'recipients' => [
                        'signers' => [[
                            'email' => $signerEmail,
                            'name' => $signerName,
                            'recipientId' => '1',
                            'routingOrder' => '1',
                            'tabs' => [
                                'signHereTabs' => [[
                                    'documentId' => '1',
                                    'pageNumber' => '1',
                                    'xPosition' => '100',
                                    'yPosition' => '150',
                                ]],
                            ],
                            'embeddedRecipientStartURL' => $returnUrl,
                        ]],
                    ],
                ]);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => 'DocuSign envelope failed: '.$this->parseError($response),
                ];
            }

            return [
                'success' => true,
                'message' => 'DocuSign envelope sent.',
                'envelope_id' => (string) $response->json('envelopeId', ''),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'DocuSign unavailable: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Resolve a DocuSign OAuth access token via JWT Grant when user_id + RSA
     * private key are configured. Falls back to treating integration_key as a
     * pre-issued access token for local demos.
     */
    private function resolveAccessToken(): ?string
    {
        $integrationKey = (string) config('docusign.integration_key', '');
        $userId = (string) config('docusign.user_id', '');
        $privateKey = $this->privateKeyPem();

        if ($integrationKey !== '' && $userId !== '' && $privateKey !== '') {
            $cacheKey = 'docusign.jwt_access_token.'.md5($integrationKey.'|'.$userId);

            return Cache::remember($cacheKey, now()->addMinutes(45), function () use ($integrationKey, $userId, $privateKey) {
                return $this->requestJwtAccessToken($integrationKey, $userId, $privateKey);
            }) ?: null;
        }

        // Legacy / demo: some environments store a ready access token as the key.
        return $integrationKey !== '' ? $integrationKey : null;
    }

    private function requestJwtAccessToken(string $integrationKey, string $userId, string $privateKeyPem): ?string
    {
        $oauthHost = rtrim((string) config('docusign.oauth_host', 'account-d.docusign.com'), '/');
        $assertion = $this->buildJwtAssertion($integrationKey, $userId, $oauthHost, $privateKeyPem);

        if ($assertion === null) {
            return null;
        }

        $response = Http::asForm()
            ->timeout((int) config('docusign.timeout', 20))
            ->post("https://{$oauthHost}/oauth/token", [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $token = $response->json('access_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function buildJwtAssertion(
        string $integrationKey,
        string $userId,
        string $oauthHost,
        string $privateKeyPem,
    ): ?string {
        $now = time();
        $header = $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'RS256'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $integrationKey,
            'sub' => $userId,
            'aud' => $oauthHost,
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => 'signature impersonation',
        ], JSON_THROW_ON_ERROR));

        $unsigned = $header.'.'.$payload;
        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false) {
            return null;
        }

        $signature = '';
        if (! openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        return $unsigned.'.'.$this->base64UrlEncode($signature);
    }

    private function privateKeyPem(): string
    {
        $inline = (string) config('docusign.private_key', '');
        if ($inline !== '') {
            return str_contains($inline, '\n')
                ? str_replace('\n', "\n", $inline)
                : $inline;
        }

        $path = (string) config('docusign.private_key_path', '');
        if ($path !== '' && is_readable($path)) {
            return (string) file_get_contents($path);
        }

        return '';
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function parseError(\Illuminate\Http\Client\Response $response): string
    {
        $json = $response->json();
        if (is_array($json)) {
            return (string) ($json['message'] ?? $json['errorCode'] ?? $response->body());
        }

        return $response->body() ?: 'HTTP '.$response->status();
    }
}
