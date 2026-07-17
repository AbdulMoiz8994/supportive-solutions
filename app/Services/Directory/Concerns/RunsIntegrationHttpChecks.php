<?php

namespace App\Services\Directory\Concerns;

use Illuminate\Support\Facades\Http;

trait RunsIntegrationHttpChecks
{
    /**
     * @return array{reachable: bool, detail: string, duration_ms: int, status_code: ?int}
     */
    protected function probeUrl(string $url, int $timeoutSeconds = 15, string $method = 'head'): array
    {
        $started = microtime(true);

        try {
            $request = Http::timeout($timeoutSeconds)
                ->withHeaders(['User-Agent' => 'BeydounTech-IntegrationHealth/1.0']);

            $response = strtolower($method) === 'get'
                ? $request->get($url)
                : $request->send($method, $url);

            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $status = $response->status();
            $reachable = $response->successful() || in_array($status, [301, 302, 401, 403, 405], true);

            return [
                'reachable' => $reachable,
                'detail' => $reachable
                    ? 'HTTP '.$status.' in '.$durationMs.'ms'
                    : 'HTTP '.$status.' from '.$url,
                'duration_ms' => $durationMs,
                'status_code' => $status,
            ];
        } catch (\Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);

            return [
                'reachable' => false,
                'detail' => $exception->getMessage(),
                'duration_ms' => $durationMs,
                'status_code' => null,
            ];
        }
    }
}
