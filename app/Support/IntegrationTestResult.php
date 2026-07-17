<?php

namespace App\Support;

use App\Models\GlobalIntegrationHealth;

class IntegrationTestResult
{
    /** @var list<array{name: string, passed: bool, detail: string, duration_ms: ?int}> */
    protected array $checks = [];

    protected ?int $startedAt = null;

    public function __construct(
        public bool $success = false,
        public string $status = GlobalIntegrationHealth::STATUS_ERROR,
        public string $message = '',
        public ?string $method = null,
        public ?string $recommendation = null,
    ) {
        $this->startedAt = (int) round(microtime(true) * 1000);
    }

    public static function make(
        bool $success,
        string $status,
        string $message,
        ?string $method = null,
    ): self {
        return new self($success, $status, $message, $method);
    }

    public function check(string $name, bool $passed, string $detail, ?int $durationMs = null): self
    {
        $this->checks[] = [
            'name' => $name,
            'passed' => $passed,
            'detail' => $detail,
            'duration_ms' => $durationMs,
        ];

        return $this;
    }

    public function recommend(string $recommendation): self
    {
        $this->recommendation = $recommendation;

        return $this;
    }

    public function failed(string $status, string $message): self
    {
        $this->success = false;
        $this->status = $status;
        $this->message = $message;

        return $this;
    }

    public function passed(string $message): self
    {
        $this->success = true;
        $this->status = GlobalIntegrationHealth::STATUS_CONNECTED;
        $this->message = $message;

        return $this;
    }

    public function partial(string $message): self
    {
        $this->success = false;
        $this->status = GlobalIntegrationHealth::STATUS_PARTIAL;
        $this->message = $message;

        return $this;
    }

    public function notConfigured(string $message): self
    {
        return $this->failed(GlobalIntegrationHealth::STATUS_NOT_CONFIGURED, $message);
    }

    /**
     * @return list<array{name: string, passed: bool, detail: string, duration_ms: ?int}>
     */
    public function checks(): array
    {
        return $this->checks;
    }

    public function passedChecks(): int
    {
        return collect($this->checks)->where('passed', true)->count();
    }

    public function totalChecks(): int
    {
        return count($this->checks);
    }

    public function latencyMs(): int
    {
        if ($this->startedAt === null) {
            return 0;
        }

        return max(0, (int) round(microtime(true) * 1000) - $this->startedAt);
    }

    /**
     * @return array{success: bool, status: string, message: string, method: ?string, recommendation: ?string, checks: list<array{name: string, passed: bool, detail: string, duration_ms: ?int}>, latency_ms: int, summary: string}
     */
    public function toArray(): array
    {
        $passed = $this->passedChecks();
        $total = $this->totalChecks();
        $summary = $total > 0
            ? sprintf('%d/%d checks passed · %dms', $passed, $total, $this->latencyMs())
            : $this->message;

        return [
            'success' => $this->success,
            'status' => $this->status,
            'message' => $this->message,
            'method' => $this->method,
            'recommendation' => $this->recommendation,
            'checks' => $this->checks,
            'latency_ms' => $this->latencyMs(),
            'summary' => $summary,
        ];
    }

    /**
     * @param  array{success: bool, status: string, message: string, method?: ?string, recommendation?: ?string, checks?: list<array{name: string, passed: bool, detail: string, duration_ms: ?int}>, latency_ms?: int, summary?: string}  $payload
     */
    public static function fromArray(array $payload): self
    {
        $result = new self(
            (bool) ($payload['success'] ?? false),
            (string) ($payload['status'] ?? GlobalIntegrationHealth::STATUS_ERROR),
            (string) ($payload['message'] ?? ''),
            $payload['method'] ?? null,
            $payload['recommendation'] ?? null,
        );

        foreach ($payload['checks'] ?? [] as $check) {
            $result->check(
                (string) ($check['name'] ?? 'Check'),
                (bool) ($check['passed'] ?? false),
                (string) ($check['detail'] ?? ''),
                isset($check['duration_ms']) ? (int) $check['duration_ms'] : null,
            );
        }

        return $result;
    }
}
