<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInboundCommunicationJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CommunicationWebhookController extends Controller
{
    public function ringCentral(Request $request): Response|SymfonyResponse
    {
        if ($validationToken = $request->header('Validation-Token')) {
            return response($validationToken, 200)->header('Validation-Token', $validationToken);
        }

        $secret = config('communications.inbound.webhook_secret');

        if ($secret && $request->header('X-Communications-Webhook-Secret') !== $secret) {
            abort(403, 'Invalid webhook secret.');
        }

        ProcessInboundCommunicationJob::dispatch($request->all(), 'ringcentral');

        return response()->noContent();
    }

    public function retell(Request $request): Response|SymfonyResponse
    {
        $secret = config('retell.webhook_secret');

        if ($secret && $request->header('X-Retell-Signature') !== $secret && $request->header('X-Communications-Webhook-Secret') !== $secret) {
            abort(403, 'Invalid Retell webhook secret.');
        }

        ProcessInboundCommunicationJob::dispatch($request->all(), 'retell');

        return response()->noContent();
    }
}
