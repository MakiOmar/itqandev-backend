<?php

namespace App\Services\Forms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class FormCaptchaVerifier
{
    public static function verify(string $provider, ?string $token, ?string $ip): bool
    {
        $provider = strtolower(trim($provider));
        if ($provider === '' || $provider === 'none') {
            return true;
        }
        $token = is_string($token) ? trim($token) : '';
        if ($token === '') {
            return false;
        }

        return match ($provider) {
            'turnstile' => self::verifyTurnstile($token, $ip),
            'recaptcha_v2', 'recaptcha_v3' => self::verifyRecaptcha($token, $ip, $provider === 'recaptcha_v3'),
            default => false,
        };
    }

    private static function verifyTurnstile(string $token, ?string $ip): bool
    {
        $secret = (string) config('services.turnstile.secret_key', '');
        if ($secret === '') {
            Log::warning('Turnstile secret missing; rejecting captcha.');

            return false;
        }

        $payload = [
            'secret' => $secret,
            'response' => $token,
        ];
        if ($ip) {
            $payload['remoteip'] = $ip;
        }

        $response = Http::asForm()->timeout(8)->post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            $payload
        );

        return $response->successful() && (bool) $response->json('success');
    }

    private static function verifyRecaptcha(string $token, ?string $ip, bool $v3): bool
    {
        $secret = (string) config('services.recaptcha.secret_key', '');
        if ($secret === '') {
            Log::warning('reCAPTCHA secret missing; rejecting captcha.');

            return false;
        }

        $payload = [
            'secret' => $secret,
            'response' => $token,
        ];
        if ($ip) {
            $payload['remoteip'] = $ip;
        }

        $response = Http::asForm()->timeout(8)->post(
            'https://www.google.com/recaptcha/api/siteverify',
            $payload
        );
        if (! $response->successful() || ! (bool) $response->json('success')) {
            return false;
        }

        if ($v3) {
            $score = (float) $response->json('score', 0);
            $min = (float) config('services.recaptcha.min_score', 0.5);

            return $score >= $min;
        }

        return true;
    }
}
