<?php

namespace App\Exceptions\Ai;

use RuntimeException;

/**
 * Raised by ClaudeService for any non-success condition. `kind` lets callers
 * (and tests) branch deterministically without string-matching messages.
 */
class ClaudeException extends RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured';
    public const AUTH = 'auth';
    public const RATE_LIMITED = 'rate_limited';
    public const HTTP = 'http';
    public const CONNECTION = 'connection';
    public const EMPTY_RESPONSE = 'empty_response';

    public function __construct(public string $kind, string $message, public ?int $status = null)
    {
        parent::__construct($message);
    }

    public static function notConfigured(): self
    {
        return new self(self::NOT_CONFIGURED, 'Anthropic API key is not configured (set ANTHROPIC_API_KEY).');
    }

    public static function auth(): self
    {
        return new self(self::AUTH, 'Anthropic rejected the API key (401).', 401);
    }

    public static function rateLimited(): self
    {
        return new self(self::RATE_LIMITED, 'Anthropic rate limit reached (429). Try again shortly.', 429);
    }

    public static function http(int $status, string $body): self
    {
        return new self(self::HTTP, "Anthropic request failed ($status): ".\Illuminate\Support\Str::limit($body, 300), $status);
    }

    public static function connection(string $message): self
    {
        return new self(self::CONNECTION, 'Could not reach Anthropic: '.$message);
    }

    public static function emptyResponse(): self
    {
        return new self(self::EMPTY_RESPONSE, 'Anthropic returned an empty response.');
    }

    public function isRetryable(): bool
    {
        return in_array($this->kind, [self::RATE_LIMITED, self::CONNECTION], true)
            || ($this->status !== null && $this->status >= 500);
    }
}
