<?php

namespace Tests\Unit;

use App\Support\WesternDigits;
use PHPUnit\Framework\TestCase;

class WesternDigitsTest extends TestCase
{
    public function test_normalizes_eastern_arabic_indic_digits(): void
    {
        $this->assertSame('mo6amed.maki', WesternDigits::normalize('mo٦amed.maki'));
        $this->assertSame('0123456789', WesternDigits::normalize('٠١٢٣٤٥٦٧٨٩'));
    }

    public function test_normalizes_extended_arabic_indic_digits(): void
    {
        $this->assertSame('0912345678', WesternDigits::normalize('۰۹۱۲۳۴۵۶۷۸'));
    }

    public function test_leaves_ascii_and_letters_unchanged(): void
    {
        $this->assertSame('user@example.com', WesternDigits::normalize('user@example.com'));
        $this->assertSame('+20-100', WesternDigits::normalize('+20-100'));
    }
}
