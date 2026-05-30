<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MediaSettings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaWebpConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    public function test_png_upload_is_converted_to_webp_when_enabled(): void
    {
        if (! function_exists('imagecreatefrompng') || ! function_exists('imagewebp')) {
            $this->markTestSkipped('GD WebP support is not available in this PHP build.');
        }

        Config::set('media.convert_to_webp', true);
        Storage::disk('local')->put('project-settings.json', json_encode([
            'media_convert_to_webp' => true,
        ]));

        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $png = UploadedFile::fake()->image('photo.png', 40, 30);

        $response = $this->withHeaders($this->bearerHeaders($admin))
            ->post('/api/v1/media/upload', [
                'file' => $png,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.mime_type', 'image/webp');
        $this->assertStringEndsWith('.webp', (string) $response->json('data.file_name'));
        $this->assertTrue(MediaSettings::convertToWebpEnabled());
    }

    public function test_png_upload_stays_png_when_conversion_disabled(): void
    {
        Config::set('media.convert_to_webp', false);
        Storage::disk('local')->put('project-settings.json', json_encode([
            'media_convert_to_webp' => false,
        ]));

        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $png = UploadedFile::fake()->image('photo.png', 40, 30);

        $response = $this->withHeaders($this->bearerHeaders($admin))
            ->post('/api/v1/media/upload', [
                'file' => $png,
            ]);

        $response->assertCreated();
        $this->assertStringContainsString('png', strtolower((string) $response->json('data.mime_type')));
    }
}
