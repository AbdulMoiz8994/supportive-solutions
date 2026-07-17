<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCaregiver;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\DocumentResource;
use App\Models\Client;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Capture-and-upload a document (ID, signed form, mail) from the field and file
 * it against a client's record — the "Submit To EMR" screen.
 */
class DocumentController extends Controller
{
    use ResolvesCaregiver;

    private const TYPES = ['ID', 'Mail/Letter', 'Signed Form', 'Other'];

    /**
     * Documents the caregiver has uploaded, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->caregiver();

        $documents = Document::query()
            ->where('uploaded_by', $request->user()->id)
            ->with('documentable')
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 25) ?: 25, 100));

        return DocumentResource::collection($documents);
    }

    /**
     * Upload a captured document. Files against a client (client_id) when the
     * client is assigned to the caregiver; otherwise against the caregiver's
     * own record.
     */
    public function store(Request $request): JsonResponse
    {
        $caregiver = $this->caregiver();

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,heic,webp'],
            'type' => ['required', Rule::in(self::TYPES)],
            'client_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Resolve what the document is attached to.
        if (! empty($data['client_id'])) {
            abort_unless(
                $this->assignedClientIds($caregiver)->contains((int) $data['client_id']),
                422,
                'This client is not assigned to you.'
            );
            $documentable = Client::findOrFail($data['client_id']);
        } else {
            $documentable = $caregiver;
        }

        $file = $request->file('file');
        $path = $file->store('documents', 'public');

        $document = new Document([
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => 'public',
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'original_filename' => $file->getClientOriginalName(),
            'type' => $data['type'],
            'category' => 'Field Upload',
            'is_signed' => $data['type'] === 'Signed Form',
            'verification_status' => 'Pending',
            'uploaded_by' => $request->user()->id,
        ]);

        $document->documentable()->associate($documentable);
        $document->save();

        return response()->json([
            'message' => 'Document uploaded.',
            'data' => (new DocumentResource($document->load('documentable')))->toArray($request),
        ], 201);
    }
}
