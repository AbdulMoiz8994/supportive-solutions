<?php

use App\Models\Contact;
use App\Models\IntegrationConnectionHealth;
use App\Models\IntegrationCredential;
use App\Services\Communication\CommunicationAiSummaryService;
use App\Services\Directory\IntegrationConnectionHealthRecorder;

test('ai summary service detects billing intent locally', function () {
    $summary = app(CommunicationAiSummaryService::class)->summarize(
        'I have a question about my MCO billing claim for last month.',
        'Billing question',
        'sms',
        'inbound',
        'Jane Client',
    );

    expect($summary)->toContain('billing');
});

test('integration health recorder updates last batch for linked directory cards', function () {
    $org = test()->createOrganization();
    $contact = test()->createContact($org->id, [
        'name' => 'AccountantsWorld',
        'type' => Contact::TYPE_VENDOR,
        'integration_slug' => 'accountantsworld',
        'integration_credential_key' => IntegrationCredential::KEY_ACCOUNTANTSWORLD,
    ]);

    app(IntegrationConnectionHealthRecorder::class)->recordBatch(IntegrationCredential::KEY_ACCOUNTANTSWORLD);

    $health = IntegrationConnectionHealth::query()->where('contact_id', $contact->id)->first();

    expect($health)->not->toBeNull()
        ->and($health->last_batch_at)->not->toBeNull()
        ->and($health->last_sync_at)->not->toBeNull();
});
