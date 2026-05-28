<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Skill;
use App\Models\SkillTranslation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminContentListLocaleTest extends TestCase
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
        ];
    }

    public function test_admin_categories_list_excludes_records_without_arabic_content(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $category = Category::create([
            'name' => 'English Only Category',
            'slug' => 'english-only-category',
            'content_locale' => null,
            'description' => 'Default locale copy',
        ]);

        $this->withHeaders($this->bearerHeaders($admin, 'ar'))
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonMissing(['slug' => $category->slug]);
    }

    public function test_admin_categories_list_includes_records_with_arabic_translation(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $category = Category::create([
            'name' => 'English Only Category',
            'slug' => 'english-only-category',
            'content_locale' => null,
            'description' => 'Default locale copy',
        ]);

        CategoryTranslation::create([
            'category_id' => $category->id,
            'locale' => 'ar',
            'name' => 'فئة عربية',
            'description' => 'وصف عربي',
        ]);

        $this->withHeaders($this->bearerHeaders($admin, 'ar'))
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonFragment(['name' => 'فئة عربية']);
    }

    public function test_admin_skills_list_excludes_records_without_arabic_content(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $skill = Skill::create([
            'name' => 'English Only Skill',
            'slug' => 'english-only-skill',
            'content_locale' => null,
            'description' => 'Default locale copy',
        ]);

        $this->withHeaders($this->bearerHeaders($admin, 'ar'))
            ->getJson('/api/v1/skills')
            ->assertOk()
            ->assertJsonMissing(['slug' => $skill->slug]);
    }

    public function test_admin_skills_list_includes_records_with_arabic_translation(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $skill = Skill::create([
            'name' => 'English Only Skill',
            'slug' => 'english-only-skill-ar',
            'content_locale' => null,
            'description' => 'Default locale copy',
        ]);

        SkillTranslation::create([
            'skill_id' => $skill->id,
            'locale' => 'ar',
            'name' => 'مهارة عربية',
            'description' => 'وصف عربي',
        ]);

        $this->withHeaders($this->bearerHeaders($admin, 'ar'))
            ->getJson('/api/v1/skills')
            ->assertOk()
            ->assertJsonFragment(['name' => 'مهارة عربية']);
    }
}
