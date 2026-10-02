<?php

namespace Tests\Unit;

use App\Services\Forms\FormLayoutDocument;
use Tests\TestCase;

class FormLayoutDocumentTest extends TestCase
{
    public function test_keeps_repeated_field_types(): void
    {
        $consent = fn (string $name) => ['type' => 'consent', 'settings' => ['name' => $name, 'label' => $name]];

        $out = FormLayoutDocument::normalizeLayout([
            'rows' => [
                ['fields' => [$consent('a'), $consent('b'), $consent('c'), $consent('d')]],
            ],
        ]);

        $this->assertSame(
            ['consent', 'consent', 'consent', 'consent'],
            array_column($out['rows'][0]['fields'], 'type'),
        );
    }
}
