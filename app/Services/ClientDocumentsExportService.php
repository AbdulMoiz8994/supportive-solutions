<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ClientDocumentsExportService
{
    public function __construct(
        protected DocumentStorageService $documentStorage
    ) {}

    /**
     * @return array<int, array{document: Document, folder: string}>
     */
    public function groupedDocuments(Client $client): array
    {
        return $client->documents
            ->sortByDesc('created_at')
            ->map(function (Document $document) {
                return [
                    'document' => $document,
                    'folder' => $this->folderFor($document),
                ];
            })
            ->values()
            ->all();
    }

    public function folderFor(Document $document): string
    {
        $category = strtolower((string) ($document->category ?? ''));
        $type = strtolower((string) ($document->type ?? ''));
        $name = strtolower((string) $document->name);

        if (str_contains($category, 'authorization') || str_contains($name, 'pa')) {
            return 'authorizations';
        }

        if (str_contains($category, 'compliance') || str_contains($name, 'compliance')) {
            return 'compliance';
        }

        // Signed FormSubmission PDFs (non-compliance) and any document typed as form.
        if ($type === 'form' || $category === 'forms') {
            return 'forms';
        }

        if (str_contains($category, 'billing') || str_contains($name, 'claim') || str_contains($name, 'eob')) {
            return 'billing';
        }

        if (str_contains($category, 'eligibility')) {
            return 'eligibility';
        }

        if (str_contains($category, 'intake') || str_contains($name, 'id')) {
            return 'intake';
        }

        return 'general';
    }

    public function downloadAll(Client $client): StreamedResponse
    {
        $documents = $client->documents->filter(function (Document $document) {
            $disk = $document->disk ?: DocumentStorageService::DISK;

            return Storage::disk($disk)->exists($document->path);
        });

        if ($documents->isEmpty()) {
            abort(404, 'No downloadable documents are available for this client.');
        }

        $zipName = 'client-'.$client->id.'-documents-'.now()->format('Y-m-d').'.zip';
        $tempPath = storage_path('app/temp/'.$zipName);

        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        $zip = new ZipArchive;
        $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($documents as $document) {
            $disk = $document->disk ?: DocumentStorageService::DISK;
            $contents = Storage::disk($disk)->get($document->path);
            $filename = $document->original_filename ?: $document->name ?: basename($document->path);
            $zip->addFromString($this->folderFor($document).'/'.$filename, $contents);
        }

        $zip->close();

        return response()->streamDownload(function () use ($tempPath) {
            readfile($tempPath);
            @unlink($tempPath);
        }, $zipName, ['Content-Type' => 'application/zip']);
    }
}
