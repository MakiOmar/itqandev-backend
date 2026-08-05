<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SearchReplaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        config([
            'search-replace.confirm_phrase' => 'CONFIRM',
            'search-replace.sample_limit' => 20,
        ]);

        Schema::create('sr_demo_items', function ($table): void {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->text('body')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();
        });

        DB::table('sr_demo_items')->insert([
            [
                'title' => 'Hello Credocode World',
                'slug' => 'hello-credocode-world',
                'body' => 'Visit Credocode today',
                'url' => 'https://credocode.example/hello',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Other row',
                'slug' => 'other-row',
                'body' => 'No match here',
                'url' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
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
        $user = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($user);

        return $user;
    }

    public function test_company_cannot_list_tables(): void
    {
        $user = User::factory()->create();
        $user->assignRole('company');

        $this->withHeaders($this->bearerHeaders($user))
            ->getJson('/api/v1/system/search-replace/tables')
            ->assertForbidden();
    }

    public function test_admin_can_list_tables_including_demo(): void
    {
        $admin = $this->admin();

        $names = collect(
            $this->withHeaders($this->bearerHeaders($admin))
                ->getJson('/api/v1/system/search-replace/tables')
                ->assertOk()
                ->json('data')
        )->pluck('name');

        $this->assertTrue($names->contains('sr_demo_items'));
        $this->assertFalse($names->contains('migrations'));
        // Never expose schema-qualified foreign-database tables.
        $this->assertFalse($names->contains(fn ($n) => is_string($n) && str_contains($n, '.')));
    }

    public function test_list_tables_is_scoped_to_connection_database(): void
    {
        $service = app(\App\Services\System\SearchReplaceService::class);
        $names = collect($service->listTables())->pluck('name');

        $this->assertTrue($names->contains('users'));
        $this->assertFalse($names->contains('migrations'));
        foreach ($names as $name) {
            $this->assertIsString($name);
            $this->assertStringNotContainsString('.', $name);
        }
    }

    public function test_preview_finds_matches_and_can_ignore_slugs(): void
    {
        $admin = $this->admin();

        $withSlugs = $this->withHeaders($this->bearerHeaders($admin))
            ->postJson('/api/v1/system/search-replace/preview', [
                'find' => 'credocode',
                'tables' => ['sr_demo_items'],
                'case_sensitive' => false,
                'ignore_slugs' => false,
            ])
            ->assertOk()
            ->json('data');

        $this->assertGreaterThanOrEqual(3, $withSlugs['match_count']);

        $ignored = $this->withHeaders($this->bearerHeaders($admin))
            ->postJson('/api/v1/system/search-replace/preview', [
                'find' => 'credocode',
                'tables' => ['sr_demo_items'],
                'case_sensitive' => false,
                'ignore_slugs' => true,
            ])
            ->assertOk()
            ->json('data');

        // slug + url columns skipped; title + body still match.
        $this->assertGreaterThanOrEqual(2, $ignored['match_count']);
        $this->assertLessThan($withSlugs['match_count'], $ignored['match_count']);
    }

    public function test_apply_requires_confirmation_phrase(): void
    {
        $admin = $this->admin();

        $this->withHeaders($this->bearerHeaders($admin))
            ->postJson('/api/v1/system/search-replace/apply', [
                'find' => 'Credocode',
                'replace' => 'CredoCode',
                'tables' => ['sr_demo_items'],
                'confirmation' => 'nope',
                'ignore_slugs' => true,
            ])
            ->assertStatus(422);
    }

    public function test_admin_can_apply_replace_with_confirm(): void
    {
        $admin = $this->admin();

        $response = $this->withHeaders($this->bearerHeaders($admin))
            ->postJson('/api/v1/system/search-replace/apply', [
                'find' => 'Credocode',
                'replace' => 'AcmeCo',
                'tables' => ['sr_demo_items'],
                'case_sensitive' => true,
                'ignore_slugs' => true,
                'confirmation' => 'CONFIRM',
            ])
            ->assertOk();

        $this->assertGreaterThanOrEqual(1, (int) $response->json('data.replaced_count'));

        $this->assertDatabaseHas('sr_demo_items', [
            'title' => 'Hello AcmeCo World',
        ]);
        // Slug ignored — unchanged.
        $this->assertDatabaseHas('sr_demo_items', [
            'slug' => 'hello-credocode-world',
        ]);
    }
}
