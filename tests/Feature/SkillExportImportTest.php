<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\SkillTranslation;
use App\Models\User;
use App\Support\ContentExportEnvelope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SkillExportImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::flush();
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(User $user, string $contentLocale = 'ar'): array
    {
        return [
            'Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken,
            'X-Content-Locale' => $contentLocale,
            'Accept' => 'application/json',
        ];
    }

    public function test_skill_export_includes_translated_fields_and_id(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $skill = Skill::create([
            'name' => 'PHP',
            'slug' => 'php-skill-export',
            'content_locale' => 'en',
            'description' => 'English',
        ]);
        SkillTranslation::create([
            'skill_id' => $skill->id,
            'locale' => 'ar',
            'name' => 'بي إتش بي',
            'description' => 'عربي',
        ]);

        $response = $this->withHeaders($this->bearerHeaders($admin, 'ar'))
            ->get('/api/v1/skills/export');

        $response->assertOk();
        $payload = json_decode($response->streamedContent(), true);
        $this->assertSame(ContentExportEnvelope::ENTITY_SKILLS, $payload['entity']);
        $item = collect($payload['items'])->firstWhere('slug', 'php-skill-export');
        $this->assertSame($skill->id, $item['id']);
        $this->assertSame('بي إتش بي', $item['name']);
    }

    public function test_import_rejects_envelope_locale_mismatch_with_header(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $envelope = ContentExportEnvelope::build(ContentExportEnvelope::ENTITY_SKILLS, 'en', [
            [
                'slug' => 'javascript',
                'name' => 'JavaScript',
                'description' => null,
                'icon_hint' => null,
            ],
        ]);

        $this->withHeaders($this->bearerHeaders($admin, 'ar'))
            ->postJson('/api/v1/skills/import', $envelope)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['locale']);
    }

    public function test_import_english_export_on_arabic_route_creates_ar_translations(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $skill = Skill::query()->where('slug', 'javascript')->first();
        $this->assertNotNull($skill);

        $envelope = ContentExportEnvelope::build(ContentExportEnvelope::ENTITY_SKILLS, 'en', [
            [
                'id' => $skill->id,
                'slug' => 'javascript',
                'name' => 'JavaScript',
                'description' => null,
                'icon_hint' => null,
            ],
        ]);

        $envelope['locale'] = 'ar';

        SkillTranslation::query()->where('skill_id', $skill->id)->where('locale', 'ar')->delete();

        $this->withHeaders($this->bearerHeaders($admin, 'ar'))
            ->postJson('/api/v1/skills/import', $envelope)
            ->assertOk()
            ->assertJsonPath('updated', 1);

        $this->assertDatabaseHas('skill_translations', [
            'skill_id' => $skill->id,
            'locale' => 'ar',
            'name' => 'JavaScript',
        ]);

        Cache::flush();

        $this->withHeaders($this->bearerHeaders($admin, 'ar'))
            ->getJson('/api/v1/skills')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'javascript', 'name' => 'JavaScript']);
    }
}
