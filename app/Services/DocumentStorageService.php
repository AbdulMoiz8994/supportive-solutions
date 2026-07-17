<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Intake;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentStorageService
{
    public const DISK = 'local';

    public const MAX_SIGNATURE_BYTES = 512000;

    /**
     * @var array<string, class-string<Model>>
     */
    protected array $documentableTypes = [
        'Client' => Client::class,
        'Employee' => Employee::class,
        'Intake' => Intake::class,
    ];

    public function resolveDocumentable(string $type, int $id): Model
    {
        $class = $this->documentableTypes[$type] ?? null;

        if (! $class) {
            throw ValidationException::withMessages([
                'documentable_type' => 'Invalid document subject type.',
            ]);
        }

        return $class::withoutGlobalScopes()->findOrFail($id);
    }

    public function assertCanAttachTo(User $user, Model $documentable): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        $userOrgId = $user->organization_id;
        $documentableOrgId = $documentable->organization_id ?? null;

        if (! $userOrgId || ! $documentableOrgId || (int) $userOrgId !== (int) $documentableOrgId) {
            abort(403, 'You cannot upload documents to records outside your organization.');
        }
    }

    public function storeUploadedFile(
        UploadedFile $file,
        Model $documentable,
        string $displayName,
        ?string $category = 'General',
        ?string $type = null
    ): Document {
        $originalFilename = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $storedFilename = Str::uuid().'.'.$extension;
        $directory = 'documents/'.$documentable->getMorphClass().'/'.$documentable->getKey();
        $path = $file->storeAs($directory, $storedFilename, self::DISK);

        return Document::create([
            'organization_id' => $documentable->organization_id,
            'name' => $displayName,
            'documentable_id' => $documentable->getKey(),
            'documentable_type' => $documentable->getMorphClass(),
            'path' => $path,
            'disk' => self::DISK,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'original_filename' => $originalFilename,
            'type' => $type,
            'category' => $category ?? 'General',
            'verification_status' => 'Pending',
            'uploaded_by' => auth()->id(),
        ]);
    }

    public function storeSignature(
        string $signatureData,
        Model $documentable,
        string $documentName
    ): Document {
        $binary = $this->decodeSignaturePayload($signatureData);
        $directory = 'signatures/'.$documentable->getMorphClass().'/'.$documentable->getKey();
        $filename = 'signature_'.time().'_'.Str::random(8).'.png';
        $path = $directory.'/'.$filename;

        Storage::disk(self::DISK)->put($path, $binary);

        return Document::create([
            'organization_id' => $documentable->organization_id,
            'name' => $documentName,
            'documentable_id' => $documentable->getKey(),
            'documentable_type' => $documentable->getMorphClass(),
            'path' => $path,
            'disk' => self::DISK,
            'mime_type' => 'image/png',
            'file_size' => strlen($binary),
            'original_filename' => $filename,
            'type' => 'Signed Agreement',
            'category' => 'Legal',
            'verification_status' => 'Verified',
            'is_signed' => true,
            'uploaded_by' => auth()->id(),
            'signed_at' => now(),
        ]);
    }

    protected function decodeSignaturePayload(string $signatureData): string
    {
        if (! preg_match('/^data:image\/png;base64,(.+)$/i', $signatureData, $matches)) {
            throw ValidationException::withMessages([
                'signature' => 'Signature must be a valid PNG data URL.',
            ]);
        }

        $encoded = $matches[1];

        if (strlen($encoded) > self::MAX_SIGNATURE_BYTES) {
            throw ValidationException::withMessages([
                'signature' => 'Signature payload is too large.',
            ]);
        }

        $binary = base64_decode($encoded, true);

        if ($binary === false || strlen($binary) === 0) {
            throw ValidationException::withMessages([
                'signature' => 'Signature could not be decoded.',
            ]);
        }

        return $binary;
    }

    public function resolveDisk(Document $document): string
    {
        if ($document->disk) {
            return $document->disk;
        }

        if (str_starts_with($document->path, 'intake-documents/')) {
            return 'public';
        }

        return self::DISK;
    }

    public function assertCanView(User $user, Document $document): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if ((int) $user->organization_id !== (int) $document->organization_id) {
            abort(403, 'You are not authorized to view this document.');
        }

        if (! $user->isEmployee()) {
            return;
        }

        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            abort(403, 'You are not authorized to view this document.');
        }

        $documentable = $document->documentable()->withoutGlobalScopes()->first();

        if (! $documentable) {
            abort(404);
        }

        if ($documentable instanceof Employee) {
            if ((int) $documentable->id !== (int) $employee->id) {
                abort(403, 'You are not authorized to view this document.');
            }

            return;
        }

        if ($documentable instanceof Client) {
            $assigned = $documentable->employees()
                ->withoutGlobalScopes()
                ->where('employees.id', $employee->id)
                ->exists();

            if (! $assigned) {
                abort(403, 'You are not authorized to view this document.');
            }

            return;
        }

        abort(403, 'You are not authorized to view this document.');
    }

    public function downloadResponse(Document $document): StreamedResponse
    {
        $disk = $this->resolveDisk($document);

        if (! Storage::disk($disk)->exists($document->path)) {
            abort(404, 'Document file not found.');
        }

        $filename = $document->original_filename ?: $document->name;

        return Storage::disk($disk)->download($document->path, $filename);
    }
}
