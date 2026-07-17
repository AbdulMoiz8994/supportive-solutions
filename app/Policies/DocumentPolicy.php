<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Policies\Concerns\InteractsWithOrganization;
use App\Services\DocumentStorageService;
use Illuminate\Database\Eloquent\Model;

class DocumentPolicy
{
    use InteractsWithOrganization;

    public function __construct(
        protected DocumentStorageService $documentStorage
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isStaff();
    }

    public function view(User $user, Document $document): bool
    {
        try {
            $this->documentStorage->assertCanView($user, $document);

            return true;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            return $exception->getStatusCode() === 403 ? false : throw $exception;
        }
    }

    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isStaff() || $user->isEmployee();
    }

    public function attach(User $user, Model $documentable): bool
    {
        try {
            $this->documentStorage->assertCanAttachTo($user, $documentable);

            return true;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            return $exception->getStatusCode() === 403 ? false : throw $exception;
        }
    }

    public function verify(User $user, Document $document): bool
    {
        if (! $this->sameOrganization($user, $document)) {
            return false;
        }

        return $this->isOfficeTeam($user);
    }
}
