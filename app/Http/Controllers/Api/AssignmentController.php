<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCaregiver;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AssignedClientResource;
use App\Models\Client;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssignmentController extends Controller
{
    use ResolvesCaregiver;

    /**
     * Clients the logged-in caregiver is assigned to (to pick from when clocking in).
     */
    public function index(): AnonymousResourceCollection
    {
        $caregiver = $this->caregiver();

        $clients = Client::query()
            ->whereIn('id', $this->assignedClientIds($caregiver))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return AssignedClientResource::collection($clients);
    }
}
