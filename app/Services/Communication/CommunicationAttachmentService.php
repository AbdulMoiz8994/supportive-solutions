<?php

namespace App\Services\Communication;

use App\Models\Communication;
use App\Models\CommunicationAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunicationAttachmentService
{
    public const DISK = 'local';

    public function storeForCommunication(Communication $communication, UploadedFile $file): CommunicationAttachment
    {
        $this->validateFile($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $storedFilename = Str::uuid().'.'.$extension;
        $directory = 'communications/'.$communication->organization_id.'/'.$communication->id;
        $path = $file->storeAs($directory, $storedFilename, self::DISK);

        return CommunicationAttachment::create([
            'communication_id' => $communication->id,
            'organization_id' => $communication->organization_id,
            'original_name' => basename($file->getClientOriginalName()),
            'stored_path' => $path,
            'disk' => self::DISK,
            'mime_type' => (string) $file->getMimeType(),
            'file_size' => (int) $file->getSize(),
        ]);
    }

    public function download(CommunicationAttachment $attachment, User $user): StreamedResponse
    {
        if (! $user->isSuperAdmin() && (int) $attachment->organization_id !== (int) $user->organization_id) {
            abort(403);
        }

        $path = $this->safePath($attachment->stored_path);

        if (! Storage::disk($attachment->disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($attachment->disk)->download($path, $attachment->original_name);
    }

    public function validateFile(UploadedFile $file): void
    {
        $maxKb = (int) config('communications.max_attachment_kilobytes', 10240);
        $allowedMimes = config('communications.allowed_attachment_mimes', []);

        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'attachment' => "Attachment may not be larger than {$maxKb} kilobytes.",
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: '');
        if ($extension && ! in_array($extension, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'attachment' => 'Attachment type is not allowed.',
            ]);
        }

        $original = $file->getClientOriginalName();
        if (str_contains($original, '..') || str_contains($original, '/') || str_contains($original, '\\')) {
            throw ValidationException::withMessages([
                'attachment' => 'Invalid attachment filename.',
            ]);
        }
    }

    protected function safePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        if (str_contains($normalized, '..')) {
            abort(403, 'Invalid file path.');
        }

        return $normalized;
    }
}
