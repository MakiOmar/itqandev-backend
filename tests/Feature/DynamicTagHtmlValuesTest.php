<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Services\Appearance\DynamicTagResolver;
use App\Services\Appearance\PageLayoutDocument;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DynamicTagHtmlValuesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::forget('project-settings');
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $project = Project::query()->create([
            'title' => 'Shop',
            'slug' => 'html-tag-shop',
            'status' => 'published',
            'summary' => 'A shop',
            'description' => '<p>First &amp; best.</p><p>Second<br>line</p>',
        ]);

        return DynamicTagResolver::payloadFromModel($project, 'project', 'en');
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function presentedSettings(string $type, array $settings, array $payload): array
    {
        $sections = [[
            'id' => 'band_tag',
            'type' => PageLayoutDocument::TYPE_LAYOUT,
            'enabled' => true,
            'rows' => [[
                'id' => 'row_tag',
                'columns' => [[
                    'id' => 'col_tag',
                    'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                    'blocks' => [[
                        'id' => 'blk_tag',
                        'kind' => 'widget',
                        'type' => $type,
                        'enabled' => true,
                        'settings' => $settings,
                    ]],
                ]],
            ]],
        ]];
        $presented = PageLayoutDocument::presentPublicForPages($sections, 'en', $payload);

        return $presented[0]['rows'][0]['columns'][0]['blocks'][0]['settings'];
    }

    public function test_html_tag_in_a_text_widget_becomes_plain_text(): void
    {
        $settings = $this->presentedSettings('text', ['content' => 'About: {{project.description}}'], $this->payload());

        $this->assertSame('About: First & best. Second line', $settings['content']);
    }

    public function test_html_tag_in_a_rich_text_widget_keeps_markup(): void
    {
        $settings = $this->presentedSettings('rich_text', ['html' => '<div>{{project.description}}</div>'], $this->payload());

        $this->assertSame('<div><p>First &amp; best.</p><p>Second<br>line</p></div>', $settings['html']);
    }

    public function test_saving_rich_text_keeps_html_and_tags_but_strips_scripts(): void
    {
        $html = $this->savedHtml('rich_text', '<p>About {{project.description}}</p><script>alert(1)</script>');

        $this->assertStringContainsString('<p>About {{project.description}}</p>', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_embed_html_stays_iframe_only(): void
    {
        $this->assertStringNotContainsString('<p>', $this->savedHtml('embed', '<p>Not allowed</p>'));
    }

    private function savedHtml(string $type, string $html): string
    {
        $document = app(\App\Services\Appearance\ChromeLayoutService::class)->normalizeDocument(['sections' => [[
            'id' => 'band_save',
            'type' => PageLayoutDocument::TYPE_LAYOUT,
            'enabled' => true,
            'rows' => [[
                'id' => 'row_save',
                'columns' => [[
                    'id' => 'col_save',
                    'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                    'blocks' => [['id' => 'blk_save', 'kind' => 'widget', 'type' => $type, 'enabled' => true, 'settings' => ['html' => $html]]],
                ]],
            ]],
        ]]]);

        return (string) $document['sections'][0]['rows'][0]['columns'][0]['blocks'][0]['settings']['html'];
    }

    public function test_text_tags_are_unchanged_and_post_content_follows_the_same_rule(): void
    {
        $payload = $this->payload();

        $this->assertSame('A shop', DynamicTagResolver::replaceString('{{project.summary}}', $payload));
        $this->assertSame('First & best. Second line', DynamicTagResolver::replaceString('{{post.content}}', $payload));
        $this->assertSame($payload['post.content'], DynamicTagResolver::replaceString('{{post.content}}', $payload, null, true));
    }
}
