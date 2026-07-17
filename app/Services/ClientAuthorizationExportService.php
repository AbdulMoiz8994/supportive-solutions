<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Document;
use App\Support\CsvStream;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;

class ClientAuthorizationExportService
{
    public function __construct(
        protected DocumentStorageService $documentStorage
    ) {}

    public function findPaLetterDocument(Client $client): ?Document
    {
        return $client->documents()
            ->where(function ($query) {
                $query->where('category', 'like', '%Authorization%')
                    ->orWhere('category', 'like', '%authorization%')
                    ->orWhere('name', 'like', '%PA%')
                    ->orWhere('name', 'like', '%Prior Authorization%')
                    ->orWhere('type', 'like', '%authorization%');
            })
            ->latest()
            ->first();
    }

    public function downloadPaLetter(Client $client): Response|StreamedResponse
    {
        $document = $this->findPaLetterDocument($client);

        if ($document && Storage::disk($document->disk ?: DocumentStorageService::DISK)->exists($document->path)) {
            return $this->documentStorage->downloadResponse($document);
        }

        $auth = $client->currentAuthorization();

        if (! $auth) {
            abort(404, 'No prior authorization letter is available for this client.');
        }

        $paNumber = 'PA-'.\Carbon\Carbon::parse($auth->start_date ?? now())->format('Y').'-'.str_pad($auth->id, 4, '0', STR_PAD_LEFT);
        $filename = strtolower($paNumber).'.html';

        $html = view('pages.clients.exports.pa-letter', [
            'client' => $client,
            'auth' => $auth,
            'paNumber' => $paNumber,
        ])->render();

        return response()->streamDownload(
            fn () => print($html),
            $filename,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    public function exportAuthorizations(Client $client): StreamedResponse
    {
        $program = $client->program_label;

        $rows = $client->careDetails->sortByDesc('end_date')->map(function ($detail) use ($program) {
            return [
                $detail->authRefForProgram($program),
                $detail->start_date ? \Carbon\Carbon::parse($detail->start_date)->format('Y-m-d') : '',
                $detail->end_date ? \Carbon\Carbon::parse($detail->end_date)->format('Y-m-d') : '',
                $detail->billing_code,
                $detail->total_units,
                $detail->effectiveStatusForProgram($program),
            ];
        });

        $endDateLabel = $program === 'DHS' ? 'Reassess Date' : 'End Date';

        return CsvStream::download(
            'client-'.$client->id.'-authorizations-'.now()->format('Y-m-d').'.csv',
            ['Auth Ref', 'Start Date', $endDateLabel, 'Service Code', 'Units', 'Status'],
            $rows
        );
    }
}
