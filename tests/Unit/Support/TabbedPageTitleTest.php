<?php

use App\Support\TabbedPageTitle;

test('global settings tab title resolves legacy aliases', function () {
    expect(TabbedPageTitle::globalSettings('integrations'))
        ->toBe('Integrations & Connections — Global Settings')
        ->and(TabbedPageTitle::globalSettings('agency'))
        ->toBe('Agency Profile — Global Settings');
});

test('client and caregiver tab titles include context name', function () {
    expect(TabbedPageTitle::client('Jane Doe', 'billing'))
        ->toBe('Billing History — Jane Doe')
        ->and(TabbedPageTitle::caregiver('Sam Helper', 'checks'))
        ->toBe('Background Checks — Sam Helper');
});

test('staff ai agents tab title varies by tab', function () {
    expect(TabbedPageTitle::staffAiAgents('agents'))->toBe('Staff & AI Agents')
        ->and(TabbedPageTitle::staffAiAgents('operations'))->toBe('AI Operations — Staff & AI Agents')
        ->and(TabbedPageTitle::staffAiAgents('staff'))->toBe('Staff — Staff & AI Agents');
});
