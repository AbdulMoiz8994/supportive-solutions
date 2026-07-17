<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApiKey\StoreApiKeyRequest;
use App\Http\Requests\ApiKey\UpdateApiKeyRequest;
use App\Models\ApiKey;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', ApiKey::class);

        $apiKeys = ApiKey::orderBy('created_at', 'desc')->get();

        return view('pages.api-keys', compact('apiKeys'), ['title' => 'API Keys']);
    }

    public function store(StoreApiKeyRequest $request)
    {
        $key = 'api_'.Str::random(32);

        $apiKey = ApiKey::create([
            'name' => $request->validated('name'),
            'key' => $key,
            'active' => true,
        ]);

        return response()->json([
            'message' => 'API Key generated successfully',
            'apiKey' => $apiKey,
        ]);
    }

    public function update(UpdateApiKeyRequest $request, $id)
    {
        $apiKey = ApiKey::findOrFail($id);
        $this->authorize('update', $apiKey);

        $apiKey->update([
            'name' => $request->validated('name'),
        ]);

        return response()->json([
            'message' => 'API Key updated successfully',
            'apiKey' => $apiKey,
        ]);
    }

    public function toggleActive($id)
    {
        $apiKey = ApiKey::findOrFail($id);
        $this->authorize('toggle', $apiKey);

        $apiKey->update([
            'active' => ! $apiKey->active,
        ]);

        return response()->json([
            'message' => 'API Key status updated successfully',
            'apiKey' => $apiKey,
        ]);
    }

    public function destroy($id)
    {
        $apiKey = ApiKey::findOrFail($id);
        $this->authorize('delete', $apiKey);
        $apiKey->delete();

        return response()->json([
            'message' => 'API Key deleted successfully',
        ]);
    }

    public function regenerate($id)
    {
        $apiKey = ApiKey::findOrFail($id);
        $this->authorize('regenerate', $apiKey);

        $newKey = 'api_'.Str::random(32);

        $apiKey->update([
            'key' => $newKey,
            'last_used_at' => now(),
        ]);

        return response()->json([
            'message' => 'API Key regenerated successfully',
            'apiKey' => $apiKey,
        ]);
    }
}
