<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Shared bot trap for public form posts. Humans leave these fields empty.
 */
final class Honeypot
{
    public static function rejectIfFilled(Request $request): void
    {
        $gotcha = trim((string) $request->input('_gotcha', ''));
        $website = trim((string) $request->input('website_url', ''));

        if ($gotcha !== '' || $website !== '') {
            throw ValidationException::withMessages([
                'form' => ['Unable to submit this form.'],
            ]);
        }
    }
}
