<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

app(\App\Services\IntegrationConfigService::class)->hydrateRuntimeConfig();

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

Cache::flush();

$clientId = config('services.availity.demo_key');
$clientSecret = config('services.availity.demo_secret');
$scopes = [
    'healthcare-hipaa-transactions-demo healthcare-hipaa-transactions-demo-demo',
    config('services.availity.scope_demo'),
];

echo "=== Availity live demo probe ===\n\n";

foreach (array_unique($scopes) as $scope) {
    echo "Scope: {$scope}\n";

    $tokenResponse = Http::asForm()->timeout(30)->post(config('services.availity.token_url'), [
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'scope' => $scope,
    ]);

    echo "  Token HTTP {$tokenResponse->status()}\n";

    if (! $tokenResponse->successful()) {
        echo '  Token error: '.($tokenResponse->json('error_description') ?? $tokenResponse->body())."\n\n";
        continue;
    }

    $token = $tokenResponse->json('access_token');
    echo '  Granted scope: '.$tokenResponse->json('scope')."\n";

    $apiBase = rtrim(config('services.availity.api_base_url'), '/');

    $probes = [
        'GET payer-list' => fn () => Http::withToken($token)->acceptJson()->get("{$apiBase}/availity-payer-list", ['limit' => 1]),
        'GET configurations 270' => fn () => Http::withToken($token)->acceptJson()->get("{$apiBase}/configurations", ['type' => '270', 'payerId' => 'BCBSF']),
        'POST claim-statuses' => fn () => Http::withToken($token)->asForm()->post("{$apiBase}/claim-statuses", [
            'payerId' => 'BCBSF',
            'providerNpi' => '1234567893',
            'patientLastName' => 'Smith',
            'patientFirstName' => 'Bob',
            'patientBirthDate' => '1980-02-12',
            'patientGenderCode' => 'M',
            'subscriberMemberId' => 'JDH001',
            'fromDate' => '2016-05-01',
            'toDate' => '2016-05-31',
        ]),
    ];

    foreach ($probes as $label => $call) {
        $response = $call();
        echo "  {$label} => HTTP {$response->status()}";
        if ($response->header('Location')) {
            echo ' location='.$response->header('Location');
        }
        echo "\n";
        if ($response->status() >= 400) {
            echo '    body: '.substr($response->body(), 0, 160)."\n";
        }
    }

    $claimBody = [
        'requestTypeCode' => 'PRE_DETERMINATION',
        'billingProvider' => [
            'npi' => '1234567893',
            'ein' => '111222333',
            'payerAssignedProviderId' => 'XYZ321',
        ],
        'patient' => [
            'relationshipCode' => '01',
            'lastName' => 'Smith',
            'firstName' => 'Bob',
            'stateCode' => 'FL',
            'birthDate' => '1980-02-12',
            'genderCode' => 'M',
        ],
        'payer' => ['id' => 'BCBSF'],
        'submitter' => ['id' => '123456789', 'lastName' => 'SUBMITTER'],
        'subscriber' => ['memberId' => 'JDH001', 'groupName' => 'ASDF 1-2', 'groupNumber' => '12312412'],
        'claimInformation' => [
            'placeOfServiceCode' => '11',
            'diagnoses' => [['qualifierCode' => 'ABK', 'code' => 'J3089']],
            'serviceLines' => [[
                'procedureCode' => '92523',
                'quantity' => '100',
                'amount' => '250',
                'fromDate' => '2016-05-10',
            ]],
        ],
    ];

    $claimResponse = Http::withToken($token)
        ->acceptJson()
        ->timeout(30)
        ->post("{$apiBase}/professional-claims", $claimBody);

    echo "  POST /professional-claims => HTTP {$claimResponse->status()}\n";
    echo '  Location: '.($claimResponse->header('Location') ?: '(none)')."\n";
    echo '  Body: '.substr($claimResponse->body(), 0, 400)."\n";

    if ($claimResponse->status() === 202 && $claimResponse->header('Location')) {
        $pollUrl = $claimResponse->header('Location');
        $poll = Http::withToken($token)->acceptJson()->timeout(30)->get($pollUrl);
        echo "  GET poll => HTTP {$poll->status()}\n";
        echo '  Poll body: '.substr($poll->body(), 0, 400)."\n";
    }

    echo "\n";
}

// Try combined scope explicitly if single scope failed claim POST above.
$scope = 'healthcare-hipaa-transactions-demo healthcare-hipaa-transactions-demo-demo';
echo "Retry combined scope: {$scope}\n";
$tokenResponse = Http::asForm()->timeout(30)->post(config('services.availity.token_url'), [
    'grant_type' => 'client_credentials',
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'scope' => $scope,
]);
echo "  Token HTTP {$tokenResponse->status()}\n";
if ($tokenResponse->successful()) {
    $token = $tokenResponse->json('access_token');
    echo '  Granted scope: '.$tokenResponse->json('scope')."\n";
    $apiBase = rtrim(config('services.availity.api_base_url'), '/');
    $claimResponse = Http::withToken($token)->acceptJson()->timeout(30)->post("{$apiBase}/professional-claims", $claimBody);
    echo "  POST /professional-claims => HTTP {$claimResponse->status()}\n";
    echo '  Location: '.($claimResponse->header('Location') ?: '(none)')."\n";
    echo '  Body: '.substr($claimResponse->body(), 0, 400)."\n";
}

echo "\n=== Claim status inquiry (276) sample from Availity docs ===\n";
$scope = 'healthcare-hipaa-transactions-demo healthcare-hipaa-transactions-demo-demo';
$tokenResponse = Http::asForm()->timeout(30)->post(config('services.availity.token_url'), [
    'grant_type' => 'client_credentials',
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'scope' => $scope,
]);
if ($tokenResponse->successful()) {
    $token = $tokenResponse->json('access_token');
    $statusResponse = Http::withToken($token)
        ->withHeaders(['X-HTTP-Method-Override' => 'GET', 'accept' => 'application.json'])
        ->asForm()
        ->timeout(30)
        ->post("{$apiBase}/claim-statuses", [
            'payer.id' => 'BCBSF',
            'submitter.lastName' => 'SUBMITTERLASTNAME',
            'submitter.firstName' => 'SUBMITTERFIRSTNAME',
            'submitter.id' => 'SUBMITTERID',
            'providers.lastName' => 'PROVIDERLASTNAME',
            'providers.firstName' => 'PROVIDERFIRSTNAME',
            'providers.npi' => '1234567893',
            'subscriber.memberId' => 'ABC123456789',
            'subscriber.lastName' => 'SUBSCRIBERLASTNAME',
            'subscriber.firstName' => 'SUBSCRIBERFIRSTNAME',
            'patient.lastName' => 'PATIENTLASTNAME',
            'patient.firstName' => 'PATIENTFIRSTNAME',
            'patient.birthDate' => '1999-09-09',
            'patient.genderCode' => 'M',
            'patient.accountNumber' => 'PAT1ENTACC0UNTNUMB3R',
            'patient.subscriberRelationshipCode' => '01',
            'fromDate' => '2025-05-15',
            'toDate' => '2025-05-19',
            'claimNumber' => 'CL4IM2TATUSNUM8ER',
            'claimAmount' => '12345678.90',
            'facilityTypeCode' => '12',
            'frequencyTypeCode' => '1',
        ]);
    echo 'POST /claim-statuses => HTTP '.$statusResponse->status()."\n";
    echo 'Location: '.($statusResponse->header('Location') ?: '(none)')."\n";
    echo substr($statusResponse->body(), 0, 500)."\n";
}
