<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\StoreComposeEfaxRequest;
use App\Http\Requests\Communication\StoreComposeMessageRequest;
use App\Models\Client;
use App\Models\Communication;
use App\Services\Communication\CommunicationDashboardService;
use App\Services\Communication\CommunicationDirectorySearchService;
use App\Services\Communication\CommunicationSendService;
use App\Support\CommunicationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunicationComposeController extends Controller
{
    public function __construct(
        protected CommunicationSendService $sendService,
        protected CommunicationDirectorySearchService $directorySearch,
        protected CommunicationDashboardService $dashboard,
    ) {}

    public function directorySearch(Request $request): JsonResponse
    {
        $this->authorize('send', Communication::class);

        $results = $this->directorySearch->search(
            $request->user(),
            $request->string('q')->toString(),
            min(20, max(1, $request->integer('limit', 12)))
        );

        return response()->json(['results' => $results]);
    }

    public function clientDocuments(Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $documents = $client->documents()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'name', 'original_filename', 'mime_type', 'created_at'])
            ->map(fn ($doc) => [
                'id' => $doc->id,
                'name' => $doc->original_filename ?: $doc->name,
                'created_at' => $doc->created_at?->format('M j, Y'),
            ]);

        return response()->json(['documents' => $documents]);
    }

    public function storeMessage(StoreComposeMessageRequest $request): RedirectResponse
    {
        $communication = $this->sendService->sendDirectMessage($request->user(), $request->validated());

        $message = $communication->status === Communication::STATUS_SENT
            ? 'Message sent and logged.'
            : 'Message logged; delivery reported a failure.'
                .($communication->failure_reason ? ' '.$communication->failure_reason : '');

        return redirect()
            ->route('communications.index')
            ->with('success', $message);
    }

    public function storeEfax(StoreComposeEfaxRequest $request): RedirectResponse
    {
        $communication = $this->sendService->sendEfax(
            $request->user(),
            $request->only(['recipient_fax', 'contact_id', 'client_id', 'cover_note', 'document_id']),
            $request->file('attachment')
        );

        return redirect()
            ->route('communications.index')
            ->with(
                'success',
                $communication->status === Communication::STATUS_SENT
                    ? 'eFax sent and logged.'
                    : 'eFax logged; delivery reported a failure.'
            );
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Communication::class);

        $periodFilter = $request->string('period')->toString() ?: null;
        $period = $this->dashboard->resolvePeriod(
            in_array($periodFilter, ['today', 'this_week'], true) ? null : ($periodFilter ?: now()->format('Y-m'))
        );

        $query = $this->dashboard->baseQuery($period, $periodFilter)
            ->with(['sender', 'related']);

        $this->dashboard->applyTabFilter($query, $request->string('tab')->toString() ?: null);
        $this->dashboard->applyPartyFilter($query, $request->string('party')->toString() ?: null);

        if ($search = trim($request->string('search')->toString())) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $rows = $query->latest()->limit(5000)->get();
        $filename = 'communications-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Party', 'Channel', 'Direction', 'Summary', 'Handled by', 'Status', 'When']);

            foreach ($rows as $communication) {
                $presenter = CommunicationPresenter::make($communication);

                fputcsv($handle, [
                    $presenter->partyName(),
                    $presenter->channelLabel(),
                    $presenter->directionLabel(),
                    $presenter->summary(),
                    $presenter->handledLabel(),
                    $communication->status,
                    $presenter->whenLabel(),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
