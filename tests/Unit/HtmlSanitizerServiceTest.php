<?php

namespace Tests\Unit;

use App\Services\HtmlSanitizerService;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerServiceTest extends TestCase
{
    public function test_sanitize_does_not_double_encode_ampersands_in_link_href(): void
    {
        $svc = new HtmlSanitizerService();
        $input = '<p><a href="https://example.com/path?a=1&b=2">link</a></p>';
        $out = $svc->sanitize($input);
        $this->assertNotFalse($out);
        $this->assertStringNotContainsString('&amp;amp;', (string) $out, 'href must not be double entity-encoded');
        $this->assertMatchesRegularExpression('/href="[^"]*a=1(&amp;|&)b=2/', (string) $out, 'query string must survive sanitization intact');
    }

    public function test_sanitize_preserves_utf8_in_text_nodes(): void
    {
        $svc = new HtmlSanitizerService();
        $input = '<p>Euro: € — Arabic: مرحبا</p>';
        $out = $svc->sanitize($input);
        $this->assertSame($input, $out);
    }

    public function test_escape_still_entity_encodes_for_plain_text_context(): void
    {
        $svc = new HtmlSanitizerService();
        $this->assertSame('&lt;p&gt;x&lt;/p&gt;', $svc->escape('<p>x</p>'));
    }
}
