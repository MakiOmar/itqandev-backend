<?php

namespace Tests\Unit;

use App\Services\Appearance\GlobalWidgetService;
use Tests\TestCase;

class GlobalWidgetResolveTest extends TestCase
{
    public function test_missing_global_id_is_skipped(): void
    {
        $sections = [
            [
                'type' => 'layout',
                'rows' => [
                    [
                        'columns' => [
                            [
                                'blocks' => [
                                    ['kind' => 'global', 'global_id' => 999999, 'type' => 'heading'],
                                    ['kind' => 'widget', 'type' => 'heading', 'settings' => ['text' => 'Keep']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $out = GlobalWidgetService::resolveSections($sections);
        $blocks = $out[0]['rows'][0]['columns'][0]['blocks'];
        $this->assertCount(1, $blocks);
        $this->assertSame('heading', $blocks[0]['type']);
        $this->assertSame('Keep', $blocks[0]['settings']['text']);
    }
}
