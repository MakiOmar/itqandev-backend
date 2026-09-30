<?php

namespace Tests\Feature;

use App\Models\AppMedia;
use App\Models\MediaLibrary;
use App\Models\User;
use App\Services\Appearance\PageLayoutDocument;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AppearanceMediaLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
    }

    private function admin(): User
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        return $admin;
    }

    private function makeLibraryMedia(): AppMedia
    {
        return MediaLibrary::instance()
            ->addMedia(UploadedFile::fake()->image('img.jpg', 40, 30))
            ->usingFileName('img-'.Str::lower(Str::random(6)).'.jpg')
            ->toMediaCollection('default')
            ->fresh();
    }

    public function test_lookup_returns_urls_for_known_ids_and_skips_unknown_ones(): void
    {
        $a = $this->makeLibraryMedia();
        $b = $this->makeLibraryMedia();

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/v1/media/lookup?ids={$a->id},{$b->id},999999");

        $response->assertOk();
        $rows = collect($response->json('data'))->keyBy('id');
        $this->assertCount(2, $rows);
        $this->assertStringContainsString($a->file_name, $rows[$a->id]['url']);
        $this->assertStringContainsString($b->file_name, $rows[$b->id]['url']);
    }

    public function test_lookup_validates_ids(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/media/lookup')->assertStatus(422);
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/media/lookup?ids=abc')->assertStatus(422);
        $tooMany = implode(',', range(1, 201));
        $this->actingAs($admin, 'sanctum')->getJson("/api/v1/media/lookup?ids={$tooMany}")->assertStatus(422);
    }

    public function test_lookup_is_forbidden_without_media_access(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/media/lookup?ids=1')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/media/lookup?ids=1')->assertUnauthorized();
    }

    public function test_public_presentation_loads_all_referenced_media_in_one_query(): void
    {
        $image = $this->makeLibraryMedia();
        $iconA = $this->makeLibraryMedia();
        $iconB = $this->makeLibraryMedia();
        $sections = [[
            'id' => 'band_1',
            'type' => 'layout',
            'rows' => [[
                'id' => 'row_1',
                'columns' => [[
                    'id' => 'col_1',
                    'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                    'blocks' => [[
                        'id' => 'sec_hero',
                        'kind' => 'kit',
                        'type' => 'hero',
                        'settings' => [
                            'image' => $image->id,
                            'image_mobile' => $image->id,
                            'floating_icons_enabled' => true,
                            'floating_icons' => [
                                ['id' => 'i1', 'enabled' => true, 'media_id' => $iconA->id, 'motion' => 'bounce', 'x' => 5, 'y' => 5, 'size' => 48],
                                ['id' => 'i2', 'enabled' => true, 'media_id' => $iconB->id, 'motion' => 'rotate', 'x' => 9, 'y' => 9, 'size' => 48],
                            ],
                        ],
                    ]],
                ]],
            ]],
        ]];
        $this->assertEqualsCanonicalizing(
            [$image->id, $iconA->id, $iconB->id],
            PageLayoutDocument::collectMediaIds($sections),
        );

        $mediaQueries = 0;
        DB::listen(function ($query) use (&$mediaQueries) {
            if (preg_match('/from [`"]?media[`"]?\s/i', $query->sql)) {
                $mediaQueries++;
            }
        });
        $presented = PageLayoutDocument::presentPublicForPages($sections, 'en');

        $settings = $presented[0]['rows'][0]['columns'][0]['blocks'][0]['settings'];
        $this->assertStringContainsString($image->file_name, $settings['image']);
        $this->assertCount(2, $settings['floating_icons']);
        $this->assertStringContainsString($iconA->file_name, $settings['floating_icons'][0]['url']);
        $this->assertSame(1, $mediaQueries);
    }
}
