<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Allow relative storage paths or HTTPS URLs on the configured app host / CDN.
 */
class ValidatesStoragePath implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('The :attribute must be a valid URL or storage path.'));

            return;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return;
        }

        if (str_starts_with($trimmed, '/')) {
            if (! preg_match('#^/[a-zA-Z0-9_./-]+$#', $trimmed)) {
                $fail(__('The :attribute must be a valid storage path.'));
            }

            return;
        }

        if (! preg_match('#^https://#i', $trimmed)) {
            $fail(__('The :attribute must use HTTPS or a relative storage path.'));

            return;
        }

        $host = parse_url($trimmed, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            $fail(__('The :attribute must be a valid HTTPS URL.'));

            return;
        }

        $allowedHosts = array_values(array_filter(array_unique([
            parse_url((string) config('app.url'), PHP_URL_HOST) ?: null,
            parse_url((string) config('filesystems.disks.public.url', ''), PHP_URL_HOST) ?: null,
        ])));

        if ($allowedHosts !== [] && ! in_array(strtolower($host), array_map('strtolower', $allowedHosts), true)) {
            $fail(__('The :attribute must point to this application or its public storage host.'));
        }
    }
}
