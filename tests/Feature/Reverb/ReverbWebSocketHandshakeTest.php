<?php

/**
 * Proves Reverb accepts a real WebSocket Upgrade while documenting that a plain
 * HTTP GET to /app/{key} hits a known upstream TypeError (laravel/reverb#344).
 *
 * Run: php artisan test --group=reverb
 */

use Symfony\Component\Process\Process as SymfonyProcess;

beforeEach(function () {
    $this->port = 18081;
    $this->appKey = 'test-reverb-handshake-key';
    $this->reverbProcess = null;
});

afterEach(function () {
    if ($this->reverbProcess instanceof SymfonyProcess && $this->reverbProcess->isRunning()) {
        $this->reverbProcess->stop(3);
    }
});

function startReverbForHandshake(object $test): void
{
    $artisan = base_path('artisan');
    $php = PHP_BINARY;

    $test->reverbProcess = new SymfonyProcess(
        [
            $php,
            $artisan,
            'reverb:start',
            '--host=127.0.0.1',
            '--port='.$test->port,
        ],
        base_path(),
        array_merge($_ENV, $_SERVER, [
            'BROADCAST_CONNECTION' => 'reverb',
            'REVERB_APP_ID' => 'test-app-id',
            'REVERB_APP_KEY' => $test->appKey,
            'REVERB_APP_SECRET' => 'test-app-secret',
            'REVERB_HOST' => '127.0.0.1',
            'REVERB_PORT' => (string) $test->port,
            'REVERB_SCHEME' => 'http',
            'REVERB_SERVER_HOST' => '127.0.0.1',
            'REVERB_SERVER_PORT' => (string) $test->port,
            'APP_ENV' => 'local',
        ]),
    );

    $test->reverbProcess->start();

    $deadline = microtime(true) + 15;
    while (microtime(true) < $deadline) {
        if (! $test->reverbProcess->isRunning()) {
            throw new RuntimeException(
                'Reverb failed to start: '.$test->reverbProcess->getErrorOutput().$test->reverbProcess->getOutput()
            );
        }

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen('127.0.0.1', $test->port, $errno, $errstr, 0.2);
        if (is_resource($socket)) {
            fclose($socket);

            return;
        }

        usleep(100_000);
    }

    throw new RuntimeException('Timed out waiting for Reverb on port '.$test->port);
}

/**
 * @param  list<string>  $extraHeaders
 */
function rawHttpRequest(int $port, string $path, array $extraHeaders = []): string
{
    $socket = stream_socket_client(
        "tcp://127.0.0.1:{$port}",
        $errno,
        $errstr,
        3
    );

    expect($socket)->not->toBeFalse("Could not connect to Reverb: {$errstr}");

    stream_set_timeout($socket, 3);

    $hasConnection = collect($extraHeaders)->contains(
        fn (string $header) => stripos($header, 'Connection:') === 0
    );

    $headers = array_merge(
        ["GET {$path} HTTP/1.1", 'Host: 127.0.0.1'],
        $extraHeaders,
        $hasConnection ? [] : ['Connection: close'],
        ['', ''],
    );

    fwrite($socket, implode("\r\n", $headers));

    $response = '';
    while (! feof($socket)) {
        $chunk = fread($socket, 8192);
        if ($chunk === false || $chunk === '') {
            break;
        }
        $response .= $chunk;
        if (str_contains($response, "\r\n\r\n")) {
            break;
        }
    }

    fclose($socket);

    return $response;
}

test('plain HTTP GET to /app/{key} returns 500 due to upstream laravel/reverb#344', function () {
    startReverbForHandshake($this);

    $response = rawHttpRequest($this->port, '/app/'.$this->appKey);

    expect($response)->toStartWith('HTTP/1.1 500');
})->group('reverb');

test('genuine WebSocket Upgrade to /app/{key} returns 101 Switching Protocols', function () {
    startReverbForHandshake($this);

    $response = rawHttpRequest($this->port, '/app/'.$this->appKey, [
        'Upgrade: websocket',
        'Connection: Upgrade',
        'Sec-WebSocket-Version: 13',
        'Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==',
    ]);

    expect($response)->toStartWith('HTTP/1.1 101 Switching Protocols');
    expect($response)->toContain('Upgrade: websocket');
    expect($response)->toContain('X-Powered-By: Laravel Reverb');
})->group('reverb');
