<?php

namespace Tests\Unit;

use App\Services\Appearance\ContentSectionDocument;
use Tests\TestCase;

class ContentSectionDocumentTest extends TestCase
{
    public function test_keeps_repeated_kits_and_drops_unknown_types(): void
    {
        $out = ContentSectionDocument::normalizeSections([
            ['type' => 'hero', 'settings' => []],
            ['type' => 'hero', 'settings' => []],
            ['type' => 'not_a_real_type'],
        ]);

        $this->assertSame(['hero', 'hero'], array_column($out, 'type'));
    }
}
