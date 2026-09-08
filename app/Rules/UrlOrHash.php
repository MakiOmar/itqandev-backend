<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Allows an empty value, a lone "#", or a valid absolute URL.
 */
class UrlOrHash implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($value === '#') {
            return;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) !== false) {
            return;
        }

        $fail("The {$attribute} field must be a valid URL.");
    }
}
