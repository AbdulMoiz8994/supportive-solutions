<?php

use App\Models\Communication;
use App\Services\Communication\Channels\GoogleEmailChannel;
use App\Services\Communication\Channels\RingCentralFaxChannel;
use App\Services\Communication\Channels\RingCentralSmsChannel;
use App\Services\Communication\CommunicationChannelManager;
use App\Services\Integrations\GoogleWorkspaceClient;
use App\Services\Integrations\RingCentralClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('communication channel manager resolves ringcentral and google drivers from config', function () {
    config([
        'communications.channels.sms' => 'ringcentral',
        'communications.channels.fax' => 'ringcentral',
        'communications.channels.email' => 'google',
    ]);

    $manager = app(CommunicationChannelManager::class);

    expect($manager->driver(Communication::CHANNEL_SMS))->toBeInstanceOf(RingCentralSmsChannel::class)
        ->and($manager->driver(Communication::CHANNEL_FAX))->toBeInstanceOf(RingCentralFaxChannel::class)
        ->and($manager->driver(Communication::CHANNEL_EMAIL))->toBeInstanceOf(GoogleEmailChannel::class);
});

test('ringcentral client resolves extension phone number when from_number is not configured', function () {
    Cache::flush();

    config([
        'ringcentral.client_id' => 'rc-client',
        'ringcentral.client_secret' => 'rc-secret',
        'ringcentral.server_url' => 'https://platform.ringcentral.com',
        'ringcentral.from_number' => '',
        'ringcentral.jwt' => '',
    ]);

    Http::fake([
        'https://platform.ringcentral.com/restapi/oauth/token' => Http::response([
            'access_token' => 'token-abc',
            'expires_in' => 3600,
        ]),
        'https://platform.ringcentral.com/restapi/v1.0/account/~/extension/~/phone-number' => Http::response([
            'records' => [
                ['phoneNumber' => '+15551234567', 'features' => ['SmsSender']],
            ],
        ]),
        'https://platform.ringcentral.com/restapi/v1.0/account/~/extension/~/sms' => Http::response([
            'id' => 'sms-456',
        ]),
    ]);

    $client = app(RingCentralClient::class);

    expect($client->resolveFromNumber())->toBe('+15551234567');

    $result = $client->sendSms('+15559876543', 'Hello');

    expect($result['success'])->toBeTrue()
        ->and($result['provider_message_id'])->toBe('sms-456');
});

test('ringcentral client detects missing sms permission from api response', function () {
    Cache::flush();

    config([
        'ringcentral.client_id' => 'rc-client',
        'ringcentral.client_secret' => 'rc-secret',
        'ringcentral.server_url' => 'https://platform.ringcentral.com',
        'ringcentral.jwt' => 'jwt-token',
    ]);

    Http::fake([
        'https://platform.ringcentral.com/restapi/oauth/token' => Http::response([
            'access_token' => 'token-abc',
            'expires_in' => 3600,
            'scope' => 'ReadAccounts',
        ]),
        'https://platform.ringcentral.com/restapi/v1.0/account/~/extension/~/sms' => Http::response([
            'message' => 'In order to call this API endpoint, application needs to have [SMS] permission',
        ], 403),
    ]);

    $result = app(RingCentralClient::class)->testSmsSendPermission();

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('SMS permission');
});

test('ringcentral client ignores legacy string token cache entries', function () {
    Cache::flush();

    config([
        'ringcentral.client_id' => 'rc-client',
        'ringcentral.client_secret' => 'rc-secret',
        'ringcentral.server_url' => 'https://platform.ringcentral.com',
        'ringcentral.jwt' => '',
    ]);

    Cache::put('ringcentral.access_token.'.md5('rc-client'), 'legacy-token-string', 3000);

    Http::fake([
        'https://platform.ringcentral.com/restapi/oauth/token' => Http::response([
            'access_token' => 'fresh-token',
            'expires_in' => 3600,
            'scope' => 'SMS',
        ]),
        'https://platform.ringcentral.com/restapi/v1.0/account/~/extension/~' => Http::response([
            'extensionNumber' => '101',
        ]),
    ]);

    $result = app(RingCentralClient::class)->testConnection();

    expect($result['success'])->toBeTrue()
        ->and(app(RingCentralClient::class)->accessTokenScope())->toBe('SMS');
});

test('ringcentral client test connection authenticates and verifies extension', function () {
    Cache::flush();

    config([
        'ringcentral.client_id' => 'rc-client',
        'ringcentral.client_secret' => 'rc-secret',
        'ringcentral.server_url' => 'https://platform.ringcentral.com',
        'ringcentral.jwt' => '',
    ]);

    Http::fake([
        'https://platform.ringcentral.com/restapi/oauth/token' => Http::response([
            'access_token' => 'token-abc',
            'expires_in' => 3600,
        ]),
        'https://platform.ringcentral.com/restapi/v1.0/account/~/extension/~' => Http::response([
            'extensionNumber' => '101',
        ]),
    ]);

    $result = app(RingCentralClient::class)->testConnection();

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toContain('101');
});

test('google workspace client test connection verifies gmail profile', function () {
    Cache::flush();

    config([
        'google_workspace.client_id' => 'google-client',
        'google_workspace.client_secret' => 'google-secret',
        'google_workspace.delegated_user' => 'agency@example.com',
        'google_workspace.refresh_token' => 'refresh-token',
    ]);

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'google-token',
            'expires_in' => 3600,
        ]),
        'https://gmail.googleapis.com/gmail/v1/users/agency%40example.com/profile' => Http::response([
            'emailAddress' => 'agency@example.com',
        ]),
    ]);

    $result = app(GoogleWorkspaceClient::class)->testConnection();

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toContain('agency@example.com');
});
