<?php

namespace Tests\Feature;

use App\Models\AppMedia;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SvgUploadSanitizationTest extends TestCase
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

    public function test_clean_svg_upload_is_accepted(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $svg = UploadedFile::fake()->createWithContent(
            'icon.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10" fill="#000"/></svg>'
        );

        $this->withHeaders($this->bearerHeaders($admin))
            ->post('/api/v1/media/upload', ['file' => $svg])
            ->assertCreated()
            ->assertJsonPath('data.mime_type', 'image/svg+xml');
    }

    public function test_svg_with_script_is_rejected_or_stripped(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $svg = UploadedFile::fake()->createWithContent(
            'bad.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="10" height="10"/></svg>'
        );

        $response = $this->withHeaders($this->bearerHeaders($admin))
            ->post('/api/v1/media/upload', ['file' => $svg]);

        if ($response->status() === 422) {
            $response->assertUnprocessable();

            return;
        }

        $response->assertCreated();
        $media = AppMedia::query()->findOrFail($response->json('data.id'));
        $stored = Storage::disk('public')->get($media->getPathRelativeToRoot());
        $this->assertIsString($stored);
        $this->assertDoesNotMatchRegularExpression('/<\s*script\b|javascript\s*:|\bon[a-z]+\s*=|<\s*foreignObject\b/i', $stored);
    }
}
