<?php

namespace App\Services\Communication;

use App\Models\IntegrationCredential;
use App\Services\Directory\IntegrationConnectionTestService;

class CommunicationIntegrationStatusService
{
    public function __construct(
        protected IntegrationConnectionTestService $connectionTest,
    ) {}

    /**
     * @return array{
     *     ringcentral: bool,
     *     ringcentral_message: string,
     *     ringcentral_sms: bool,
     *     ringcentral_sms_message: string,
     *     ringcentral_fax: bool,
     *     ringcentral_fax_message: string,
     *     google: bool,
     *     google_message: string,
     * }
     */
    public function forCompose(): array
    {
        $ringcentral = $this->connectionTest->testCredentialKey(IntegrationCredential::KEY_RINGCENTRAL)->toArray();
        $google = $this->connectionTest->testCredentialKey(IntegrationCredential::KEY_GOOGLE_WORKSPACE)->toArray();

        $authReady = $this->integrationCheckPassed($ringcentral, 'OAuth + extension API');

        return [
            'ringcentral' => (bool) ($ringcentral['success'] ?? false),
            'ringcentral_message' => (string) ($ringcentral['message'] ?? ''),
            'ringcentral_sms' => $authReady
                && $this->integrationCheckPassed($ringcentral, 'SMS sender number')
                && $this->integrationCheckPassed($ringcentral, 'SMS API permission'),
            'ringcentral_sms_message' => $this->integrationCheckMessage($ringcentral, 'SMS API permission')
                ?: $this->integrationCheckMessage($ringcentral, 'SMS sender number')
                ?: ((bool) ($ringcentral['success'] ?? false) ? '' : (string) ($ringcentral['message'] ?? '')),
            'ringcentral_fax' => $authReady
                && $this->integrationCheckPassed($ringcentral, 'Fax API permission'),
            'ringcentral_fax_message' => $this->integrationCheckMessage($ringcentral, 'Fax API permission')
                ?: ((bool) ($ringcentral['success'] ?? false) ? '' : (string) ($ringcentral['message'] ?? '')),
            'google' => (bool) ($google['success'] ?? false),
            'google_message' => (string) ($google['message'] ?? ''),
        ];
    }

    public function ringCentralReady(): bool
    {
        return $this->forCompose()['ringcentral'];
    }

    public function ringcentralSmsReady(): bool
    {
        return $this->forCompose()['ringcentral_sms'];
    }

    public function ringcentralSmsMessage(): string
    {
        return $this->forCompose()['ringcentral_sms_message'];
    }

    public function ringcentralFaxReady(): bool
    {
        return $this->forCompose()['ringcentral_fax'];
    }

    public function ringcentralFaxMessage(): string
    {
        return $this->forCompose()['ringcentral_fax_message'];
    }

    public function googleReady(): bool
    {
        return $this->forCompose()['google'];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function integrationCheckPassed(array $result, string $name): bool
    {
        foreach ($result['checks'] ?? [] as $check) {
            if (($check['name'] ?? '') === $name) {
                return (bool) ($check['passed'] ?? false);
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function integrationCheckMessage(array $result, string $name): string
    {
        foreach ($result['checks'] ?? [] as $check) {
            if (($check['name'] ?? '') === $name && ! ($check['passed'] ?? false)) {
                return (string) ($check['detail'] ?? '');
            }
        }

        return '';
    }
}
