<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Support\ContentExportEnvelope;
use App\Support\ProjectSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PagesExportImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ProjectSettingsStore::save([
            'default_locale' => 'en',
            'site_languages' => [
                ['code' => 'en', 'label' => 'English', 'native_label' => 'English', 'rtl' => false],
                ['code' => 'ar', 'label' => 'Arabic', 'native_label' => 'العربية', 'rtl' => true],
            ],
        ]);
    }

    private function actingEditor(): User
    {
        Permission::findOrCreate('manage pages');
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('manage pages');
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function localeHeaders(string $locale = 'en'): array
    {
        return [
            'X-Content-Locale' => $locale,
            'Accept' => 'application/json',
        ];
    }

    public function test_export_includes_builder_sections_and_parent(): void
    {
        $this->actingEditor();

        $parent = Page::create([
            'title' => 'Legal',
            'slug' => 'legal',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
            'content_locale' => 'en',
            'sections' => [],
        ]);
        $child = Page::create([
            'title' => 'Privacy',
            'slug' => 'privacy',
            'excerpt' => 'Policy',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
            'content_locale' => 'en',
            'parent_id' => $parent->id,
            'exclude_from_search' => true,
            'sections' => [
                [
                    'type' => 'layout',
                    'layout_width' => 'boxed',
                    'rows' => [
                        [
                            'columns' => [
                                [
                                    'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                    'blocks' => [
                                        ['type' => 'cta', 'settings' => ['title' => 'Read policy']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->withHeaders($this->localeHeaders('en'))
            ->get('/api/v1/pages/export?ids[]='.$child->id);

        $response->assertOk();
        $payload = json_decode($response->streamedContent(), true);
        $this->assertSame(ContentExportEnvelope::FORMAT, $payload['format']);
        $this->assertSame(ContentExportEnvelope::ENTITY_PAGES, $payload['entity']);
        $this->assertCount(1, $payload['items']);
        $item = $payload['items'][0];
        $this->assertSame($child->id, $item['id']);
        $this->assertSame('privacy', $item['slug']);
        $this->assertSame('legal', $item['parent_slug']);
        $this->assertTrue($item['exclude_from_search']);
        $this->assertSame('layout', $item['sections'][0]['type']);
        $this->assertSame('cta', $item['sections'][0]['rows'][0]['columns'][0]['blocks'][0]['type']);
    }

    public function test_import_upsert_creates_nested_page_with_sections(): void
    {
        $this->actingEditor();

        Page::create([
            'title' => 'Legal',
            'slug' => 'legal',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
            'content_locale' => 'en',
            'sections' => [],
        ]);

        $envelope = ContentExportEnvelope::build(ContentExportEnvelope::ENTITY_PAGES, 'en', [
            [
                'slug' => 'terms',
                'title' => 'Terms',
                'excerpt' => 'Terms excerpt',
                'status' => 'published',
                'parent_slug' => 'legal',
                'exclude_from_search' => true,
                'sections' => [
                    [
                        'type' => 'cta',
                        'enabled' => true,
                        'settings' => ['title' => 'Agree'],
                    ],
                ],
            ],
        ]);

        $result = $this->withHeaders($this->localeHeaders('en'))
            ->postJson('/api/v1/pages/import', $envelope);

        $result->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('entity', 'pages');

        $page = Page::query()->where('slug', 'terms')->first();
        $this->assertNotNull($page);
        $this->assertSame('Terms', $page->title);
        $this->assertTrue((bool) $page->exclude_from_search);
        $this->assertNotNull($page->parent_id);
        $this->assertSame('legal', $page->parent->slug);
        $this->assertSame('layout', $page->sections[0]['type'] ?? null);
        $this->assertSame('cta', $page->sections[0]['rows'][0]['columns'][0]['blocks'][0]['type'] ?? null);
    }

    public function test_import_translation_only_updates_title_without_wiping_sections(): void
    {
        $this->actingEditor();

        $page = Page::create([
            'title' => 'About EN',
            'slug' => 'about-export',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
            'content_locale' => 'en',
            'sections' => [
                [
                    'type' => 'layout',
                    'rows' => [
                        [
                            'columns' => [
                                [
                                    'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                    'blocks' => [
                                        ['type' => 'cta', 'settings' => ['title' => 'Keep me']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $envelope = ContentExportEnvelope::build(ContentExportEnvelope::ENTITY_PAGES, 'ar', [
            [
                'id' => $page->id,
                'slug' => 'about-export',
                'title' => 'من نحن',
                'excerpt' => 'وصف',
                'sections' => [
                    ['type' => 'cta', 'settings' => ['title' => 'Should not replace in translation_only']],
                ],
            ],
        ]);

        $this->withHeaders($this->localeHeaders('ar'))
            ->postJson('/api/v1/pages/import?mode=translation_only', $envelope)
            ->assertOk()
            ->assertJsonPath('updated', 1);

        $page->refresh();
        $this->assertSame('About EN', $page->title);
        $this->assertSame('Keep me', $page->sections[0]['rows'][0]['columns'][0]['blocks'][0]['settings']['title'] ?? null);
        $this->assertSame('من نحن', $page->translations()->where('locale', 'ar')->value('title'));
    }
}
