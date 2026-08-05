<?php

namespace App\Support;

/**
 * Convert Eastern / Extended Arabic-Indic digits to ASCII 0–9.
 * Public forms use this for email and tel values before validation.
 */
final class WesternDigits
{
    private const ARABIC_INDIC = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    private const EXTENDED_ARABIC_INDIC = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    public static function normalize(string $value): string
    {
        $ascii = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace(
            array_merge(self::ARABIC_INDIC, self::EXTENDED_ARABIC_INDIC),
            array_merge($ascii, $ascii),
            $value
        );
    }
}
