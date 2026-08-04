<?php

namespace Tests\Feature;

use App\Models\AppMedia;
use App\Models\MediaLibrary;
use App\Models\MediaTranslation;
use App\Models\User;
use App\Services\Appearance\AppearanceMediaResolver;
use App\Services\Appearance\HomepageBuilderService;
use App\Support\Media\LocalizedMediaMeta;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MultilingualMediaAppearanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('local')->put('project-settings.json', json_encode([
            'site_name' => 'Test',
            'default_locale' => 'en',
            'site_languages' => [
                ['code' => 'en', 'label' => 'English', 'enabled' => true],
                ['code' => 'ar', 'label' => 'Arabic', 'enabled' => true, 'rtl' => true],
            ],
        ]));
        Cache::forget('project-settings');
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    private function admin(): User
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        return $admin;
    }

    private function makeLibraryMedia(string $altText = 'EN alt'): AppMedia
    {
        $library = MediaLibrary::instance();
        $media = $library
            ->addMedia(UploadedFile::fake()->image('hero.jpg', 40, 30))
            ->usingFileName('hero-'.Str::lower(Str::random(6)).'.jpg')
            ->toMediaCollection('default');

        $media->update(['alt_text' => $altText, 'description' => 'EN desc']);

        return $media->fresh();
    }

    public function test_localized_media_meta_falls_back_to_primary(): void
    {
        $media = $this->makeLibraryMedia('Primary alt');
        MediaTranslation::query()->create([
            'media_id' => $media->id,
            'locale' => 'ar',
            'alt_text' => 'نص بديل',
            'description' => null,
        ]);
        $media->load('translations');

        $this->assertSame('Primary alt', LocalizedMediaMeta::alt($media, 'en', 'en'));
        $this->assertSame('نص بديل', LocalizedMediaMeta::alt($media, 'ar', 'en'));
        $this->assertSame('Primary alt', LocalizedMediaMeta::alt($media, 'fr', 'en'));
        $this->assertSame('EN desc', LocalizedMediaMeta::description($media, 'ar', 'en'));
    }

    public function test_media_update_upserts_translation_and_resource_reads_columns(): void
    {
        $admin = $this->admin();
        $media = $this->makeLibraryMedia('Old EN');

        $this->withHeaders($this->bearerHeaders($admin))
            ->putJson('/api/v1/media/'.$media->id, [
                'alt_text' => 'New EN',
                'description' => 'New desc',
                'locale' => 'en',
                'translations' => [
                    'ar' => [
                        'alt_text' => 'عربي',
                        'description' => 'وصف',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.alt_text', 'New EN')
            ->assertJsonPath('data.translations.ar.alt_text', 'عربي');

        $this->assertSame('New EN', $media->fresh()->alt_text);
        $this->assertDatabaseHas('media_translations', [
            'media_id' => $media->id,
            'locale' => 'ar',
            'alt_text' => 'عربي',
        ]);

        $this->withHeaders($this->bearerHeaders($admin))
            ->getJson('/api/v1/media/'.$media->id.'?locale=ar')
            ->assertOk()
            ->assertJsonPath('data.alt_text', 'عربي');
    }

    public function test_appearance_public_resolves_media_id_to_url_and_localized_alt(): void
    {
        $media = $this->makeLibraryMedia('Hero EN');
        MediaTranslation::query()->create([
            'media_id' => $media->id,
            'locale' => 'ar',
            'alt_text' => 'Hero AR',
        ]);

        $service = app(HomepageBuilderService::class);
        $service->save([
            'sections' => [
                [
                    'type' => 'hero',
                    'enabled' => true,
                    'settings' => [
                        'headline' => 'Hello EN',
                        'image' => $media->id,
                        'translations' => [
                            'ar' => [
                                'headline' => 'مرحبا',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $en = $service->presentPublic('en');
        $this->assertNotSame('', $en[0]['settings']['image']);
        $this->assertFalse(is_int($en[0]['settings']['image']));
        $this->assertSame('Hero EN', $en[0]['settings']['image_alt']);

        $ar = $service->presentPublic('ar');
        $this->assertSame('مرحبا', $ar[0]['settings']['headline']);
        $this->assertSame($en[0]['settings']['image'], $ar[0]['settings']['image']);
        $this->assertSame('Hero AR', $ar[0]['settings']['image_alt']);
    }

    public function test_appearance_public_uses_locale_specific_media_when_set(): void
    {
        $enMedia = $this->makeLibraryMedia('EN asset');
        $arMedia = $this->makeLibraryMedia('AR asset primary');
        MediaTranslation::query()->create([
            'media_id' => $arMedia->id,
            'locale' => 'ar',
            'alt_text' => 'AR asset alt',
        ]);

        $service = app(HomepageBuilderService::class);
        $service->save([
            'sections' => [
                [
                    'type' => 'hero',
                    'enabled' => true,
                    'settings' => [
                        'image' => $enMedia->id,
                        'translations' => [
                            'ar' => [
                                'image' => $arMedia->id,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $en = $service->presentPublic('en');
        $ar = $service->presentPublic('ar');
        $this->assertNotSame($en[0]['settings']['image'], $ar[0]['settings']['image']);
        $this->assertSame('EN asset', $en[0]['settings']['image_alt']);
        $this->assertSame('AR asset alt', $ar[0]['settings']['image_alt']);
    }

    public function test_appearance_media_resolver_keeps_legacy_urls(): void
    {
        $resolved = AppearanceMediaResolver::resolve('/hero-banner.webp', 'ar', 'en');
        $this->assertSame('/hero-banner.webp', $resolved['url']);
        $this->assertNull($resolved['alt']);
        $this->assertNull($resolved['media_id']);
    }
}
