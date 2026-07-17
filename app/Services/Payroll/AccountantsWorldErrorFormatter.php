<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use Illuminate\Support\Str;

class AccountantsWorldErrorFormatter
{
    public const CONTEXT_CREATE = 'create';

    public const CONTEXT_VERIFY = 'verify';

    public const CONTEXT_LEGACY = 'legacy';

    public const CONTEXT_PAYROLL_SYNC = 'payroll_sync';

    /**
     * @param  array{success?: bool, raw?: array<string, mixed>}  $result
     */
    public function formatFromApiResult(array $result, string $context = self::CONTEXT_CREATE): string
    {
        $raw = $result['raw'] ?? [];
        $status = isset($raw['http_status']) ? (int) $raw['http_status'] : null;
        $specific = $this->extractSpecificMessage($raw);

        if ($specific && ! $this->isGenericHttpMessage($specific, $status)) {
            return $this->contextualize($specific, $context, $status);
        }

        return $this->fallbackMessage($context, $status);
    }

    public function formatStored(Employee $employee): string
    {
        if ($employee->aw_setup_error_context === self::CONTEXT_LEGACY) {
            return 'This setup was flagged before detailed error tracking was enabled. Use Retry or Verify to run a fresh check and capture the live AccountantsWorld response.';
        }

        if ($employee->aw_setup_error) {
            return $employee->aw_setup_error;
        }

        return $this->fallbackMessage(
            $employee->aw_setup_error_context ?? self::CONTEXT_CREATE,
            $employee->aw_setup_http_status
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function extractSpecificMessage(array $raw): ?string
    {
        foreach (['message', 'error', 'detail', 'body_text', 'title'] as $key) {
            if (empty($raw[$key]) || ! is_string($raw[$key])) {
                continue;
            }

            $message = $this->normalizeText($raw[$key]);

            if ($message !== '') {
                return $message;
            }
        }

        if (! empty($raw['errors']) && is_array($raw['errors'])) {
            $messages = collect($raw['errors'])->flatten()->filter(fn ($value) => is_string($value) && trim($value) !== '');

            if ($messages->isNotEmpty()) {
                return $this->normalizeText($messages->implode(' '));
            }
        }

        return null;
    }

    protected function contextualize(string $message, string $context, ?int $status): string
    {
        $message = $this->normalizeText($message);

        if ($this->looksLikeHtmlDocument($message)) {
            return $this->fallbackMessage($context, $status);
        }

        if ($context === self::CONTEXT_VERIFY && str_contains(strtolower($message), 'not found')) {
            return 'No matching employee was returned by AccountantsWorld. Double-check the AW ID, or confirm the caregiver was added in the AW portal before verifying.';
        }

        if ($status) {
            return "{$message} (HTTP {$status})";
        }

        return $message;
    }

    protected function fallbackMessage(string $context, ?int $status): string
    {
        if ($context === self::CONTEXT_VERIFY) {
            return match ($status) {
                404 => 'AccountantsWorld could not find this employee (HTTP 404). Confirm they exist in AW, or use the correct AW employee ID.',
                401, 403 => 'AccountantsWorld rejected the verification request (HTTP '.$status.'). Check the credentials for your selected auth mode in Global Settings → Credential Vault.',
                422 => 'AccountantsWorld rejected the lookup request as invalid (HTTP 422). Check the AW employee ID format.',
                500, 502, 503 => 'AccountantsWorld returned a server error (HTTP '.$status.'). Try again later.',
                0, null => 'Could not reach AccountantsWorld to verify this employee. Check the API URL and network connection.',
                default => 'AccountantsWorld could not verify this employee (HTTP '.$status.').',
            };
        }

        if ($context === self::CONTEXT_PAYROLL_SYNC) {
            return match ($status) {
                404 => 'The payroll API endpoint was not found (HTTP 404). Verify ACCOUNTANTSWORLD_API_URL points to the integration host.',
                401, 403 => 'AccountantsWorld rejected the payroll request (HTTP '.$status.'). Check the credentials for your selected auth mode in Global Settings → Credential Vault.',
                422 => 'AccountantsWorld rejected the payroll data (HTTP 422). Review hours, pay types, and employee IDs.',
                500, 502, 503 => 'AccountantsWorld returned a server error (HTTP '.$status.'). Try again later.',
                0, null => 'Could not reach AccountantsWorld payroll API. Check the API URL and network connection.',
                default => 'AccountantsWorld payroll sync failed (HTTP '.$status.').',
            };
        }

        return match ($status) {
            404 => 'The employee import endpoint was not found (HTTP 404). Verify ACCOUNTANTSWORLD_API_URL points to https://dev-api.payrollrelief.com/integration.',
            401, 403 => 'AccountantsWorld rejected the create request (HTTP '.$status.'). Check the credentials for your selected auth mode (API key, OAuth, or both) in Global Settings → Credential Vault.',
            422 => 'AccountantsWorld rejected the employee data (HTTP 422). Review name, SSN, and pay fields.',
            500, 502, 503 => 'AccountantsWorld returned a server error (HTTP '.$status.'). Try again later.',
            0, null => 'Could not reach AccountantsWorld to create this employee. Check the API URL and network connection.',
            default => 'AccountantsWorld could not create this employee (HTTP '.$status.').',
        };
    }

    protected function normalizeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    protected function looksLikeHtmlDocument(string $message): bool
    {
        return Str::length($message) > 400
            || str_contains(strtolower($message), 'html')
            || str_contains(strtolower($message), 'body {');
    }

    protected function isGenericHttpMessage(string $message, ?int $status): bool
    {
        $normalized = strtolower(trim($message));

        foreach (['not found', '404 not found', 'page not found', 'unauthorized', 'forbidden', 'internal server error'] as $generic) {
            if ($normalized === $generic) {
                return true;
            }
        }

        if ($status && str_contains($normalized, (string) $status) && Str::length($message) <= 32) {
            return true;
        }

        return false;
    }
}
