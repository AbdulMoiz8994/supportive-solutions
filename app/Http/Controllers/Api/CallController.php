<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCaregiver;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CallLogResource;
use App\Models\CallLog;
use App\Models\Client;
use App\Services\Communication\ClickToCallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Click-to-call for the caregiver app ("Call Now" on the My Clients screen).
 * A caregiver may only call a client they are assigned to.
 */
class CallController extends Controller
{
    use ResolvesCaregiver;

    public function __construct(
        protected ClickToCallService $clickToCall
    ) {}

    /**
     * The caregiver's own recent call history (newest first).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $caregiver = $this->caregiver();
        $perPage = min((int) $request->integer('per_page', 25) ?: 25, 100);

        $logs = CallLog::query()
            ->where('employee_id', $caregiver->id)
            ->latest()
            ->paginate($perPage);

        return CallLogResource::collection($logs);
    }

    /**
     * Place a call to an assigned client. Returns a RingOut record (the
     * caregiver's phone rings first) or, if RingCentral isn't available, a
     * `manual` record carrying a `tel:` link for the device to dial.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer'],
        ]);

        $caregiver = $this->caregiver();
        $clientId = (int) $data['client_id'];

        abort_unless(
            $this->assignedClientIds($caregiver)->contains($clientId),
            403,
            'This client is not assigned to you.'
        );

        $client = Client::query()->whereKey($clientId)->first();
        abort_if($client === null, 404, 'Client not found.');

        if (blank($client->phone)) {
            return response()->json(['message' => 'This client has no phone number on file.'], 422);
        }

        $log = $this->clickToCall->callClient($request->user(), $caregiver, $client);

        return response()->json([
            'message' => $log->mode === CallLog::MODE_RINGOUT
                ? 'Call initiated — your phone will ring first, then connect to the client.'
                : 'Ready to dial. Open the client number on your device.',
            'data' => (new CallLogResource($log))->toArray($request),
        ], 201);
    }
}
