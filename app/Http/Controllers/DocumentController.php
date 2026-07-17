<?php

namespace App\Http\Controllers;

use App\Http\Requests\Document\StoreDocumentRequest;
use App\Http\Requests\Document\StoreSignatureRequest;
use App\Models\Document;
use App\Services\DocumentStorageService;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    public function __construct(
        protected DocumentStorageService $documentStorage
    ) {}

    public function store(StoreDocumentRequest $request)
    {
        $documentable = $this->documentStorage->resolveDocumentable(
            $request->input('documentable_type'),
            (int) $request->input('documentable_id')
        );

        $this->documentStorage->assertCanAttachTo(auth()->user(), $documentable);

        $this->documentStorage->storeUploadedFile(
            $request->file('file'),
            $documentable,
            $request->input('name')
        );

        return redirect()->back()->with('success', 'Document uploaded successfully and queued for verification.');
    }

    public function storeSignature(StoreSignatureRequest $request)
    {
        try {
            $documentable = $this->documentStorage->resolveDocumentable(
                $request->input('documentable_type'),
                (int) $request->input('documentable_id')
            );

            $this->documentStorage->assertCanAttachTo(auth()->user(), $documentable);

            $document = $this->documentStorage->storeSignature(
                $request->input('signature'),
                $documentable,
                $request->input('document_name')
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => collect($exception->errors())->flatten()->first(),
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Digital signature saved and attached to record.',
            'document' => $document,
        ]);
    }

    public function verify($id)
    {
        $doc = Document::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('verify', $doc);
        $doc->update(['verification_status' => 'Verified']);

        return redirect()->back()->with('success', 'Document verified successfully.');
    }

    public function download($id)
    {
        $document = Document::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('download', $document);

        return $this->documentStorage->downloadResponse($document);
    }
}
