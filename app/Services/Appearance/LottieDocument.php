<?php

namespace App\Services\Appearance;

/**
 * Validate Lottie JSON payloads (no executable types).
 */
final class LottieDocument
{
    public const MAX_BYTES = 524288;

    public static function isValidJson(string $json): bool
    {
        $json = trim($json);
        if ($json === '' || strlen($json) > self::MAX_BYTES) {
            return false;
        }
        if (preg_match('/<\s*script|javascript:|expression\s*\(/i', $json) === 1) {
            return false;
        }
        $decoded = json_decode($json, true, 32);
        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        return isset($decoded['v']) || isset($decoded['layers']) || isset($decoded['assets']);
    }
}
