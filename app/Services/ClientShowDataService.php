<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\Contact;

class ClientShowDataService
{
    public function __construct(
        protected OrganizationMetricsService $metricsService
    ) {}

    public function build(Client $client): array
    {
        $caseCoordinator = $this->resolveCaseCoordinator($client);

        return [
            'statCards' => $this->metricsService->getStatCards($client->organization_id),
            'subtitle' => $this->buildSubtitle($client),
            'activityLogs' => $this->buildActivityLogs($client),
            'statusTimeline' => $this->buildStatusTimeline($client),
            'counts' => [
                'care_details' => $client->careDetails->count(),
                'requests' => $client->relationLoaded('requests') ? $client->requests->count() : ClientRequest::where('client_id', $client->id)->count(),
                'schedules' => $client->schedules->count(),
                'documents' => $client->documents->count(),
                'billings' => $client->billings->count(),
                'contacts' => $client->contacts->count(),
                'activities' => count($this->buildActivityLogs($client)),
            ],
            'caseCoordinator' => $caseCoordinator,
            'emergencyContact' => $this->resolveEmergencyContact($client),
        ];
    }

    protected function buildSubtitle(Client $client): string
    {
        $parts = array_filter([
            $client->member_id ?: null,
            $client->office_location ?: $client->county ?: null,
            $client->coverageType?->name,
            $client->created_at ? 'Since '.$client->created_at->format('M Y') : null,
        ]);

        return $parts ? implode(' · ', $parts) : '—';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildActivityLogs(Client $client): array
    {
        return ActivityLog::query()
            ->where('subject_type', Client::class)
            ->where('subject_id', $client->id)
            ->with('user')
            ->latest()
            ->take(10)
            ->get()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildStatusTimeline(Client $client): array
    {
        $entries = ClientRequest::query()
            ->where('client_id', $client->id)
            ->latest('sent_at')
            ->take(5)
            ->get()
            ->map(function (ClientRequest $request) {
                $delivery = $request->delivery_method ?: $request->method;
                $status = ucfirst(strtolower((string) $request->status));

                return [
                    'title' => $request->subject ?: $request->template ?: 'Send Request',
                    'time' => $request->sent_at?->format('M d, Y | h:i A') ?? $request->created_at?->format('M d, Y | h:i A') ?? '—',
                    'actor' => trim($delivery.' · '.$status) ?: 'System',
                    'tone' => strtolower((string) $request->status) === 'sent' ? 'green' : 'orange',
                ];
            })
            ->all();

        if ($client->status) {
            array_unshift($entries, [
                'title' => 'Current Status: '.$client->status,
                'time' => $client->updated_at?->format('M d, Y | h:i A') ?? '—',
                'actor' => 'System',
                'tone' => 'green',
            ]);
        }

        return $entries;
    }

    protected function resolveCaseCoordinator(Client $client): ?Contact
    {
        return $client->contacts->first(function (Contact $contact) {
            $role = strtolower($contact->pivot->role ?? '');

            return str_contains($role, 'coordinator') || $contact->type === 'Case Coordinator';
        }) ?? $client->contacts->first();
    }

    protected function resolveEmergencyContact(Client $client): ?Contact
    {
        return $client->contacts->first(function (Contact $contact) {
            $role = strtolower($contact->pivot->role ?? '');

            return str_contains($role, 'emergency');
        });
    }
}
