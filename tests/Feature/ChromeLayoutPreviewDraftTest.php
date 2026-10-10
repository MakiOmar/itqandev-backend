<?php

namespace Tests\Feature;

use App\Models\ChromeLayout;
use App\Models\Project;
use App\Models\ProjectTranslation;
use App\Models\User;
use App\Services\Appearance\ChromeLayoutService;
use App\Services\Appearance\PageLayoutDocument;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChromeLayoutPreviewDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::forget('project-settings');
    }

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@credocode.test')->firstOrFail();
    }

    private function single(): ChromeLayout
    {
        return app(ChromeLayoutService::class)->create(ChromeLayout::KIND_SINGLE, ['name' => 'Project single']);
    }

    /** @return list<array<string, mixed>> */
    private function unsavedSections(string $text): array
    {
        return [[
            'id' => 'band_draft',
            'type' => PageLayoutDocument::TYPE_LAYOUT,
            'enabled' => true,
            'rows' => [[
                'id' => 'row_draft',
                'columns' => [[
                    'id' => 'col_draft',
                    'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                    'blocks' => [[
                        'id' => 'blk_draft',
                        'kind' => 'widget',
                        'type' => 'heading',
                        'enabled' => true,
                        'settings' => ['text' => $text],
                    ]],
                ]],
            ]],
        ]];
    }

    public function test_admin_previews_unsaved_sections_with_record_tags_in_locale(): void
    {
        $project = Project::query()->create(['title' => 'Shop', 'slug' => 'draft-shop', 'status' => 'published']);
        ProjectTranslation::query()->create(['project_id' => $project->id, 'locale' => 'ar', 'title' => 'متجر']);
        $layout = $this->single();
        $before = $layout->fresh()->document;

        $res = $this->withHeaders($this->bearer($this->admin()))->postJson("/api/appearance/singles/{$layout->id}/preview-as", [
            'content_type' => 'project',
            'record_id' => $project->id,
            'locale' => 'ar',
            'sections' => $this->unsavedSections('{{post.title}} — {{post.url}}'),
        ]);

        $res->assertOk();
        $this->assertSame(
            'متجر — /work/draft-shop',
            $res->json('data.sections.0.rows.0.columns.0.blocks.0.settings.text'),
        );
        $this->assertSame($before, $layout->fresh()->document, 'preview must not save');
    }

    public function test_preview_draft_validates_payload(): void
    {
        $layout = $this->single();

        $this->withHeaders($this->bearer($this->admin()))
            ->postJson("/api/appearance/singles/{$layout->id}/preview-as", ['content_type' => 'nope', 'record_id' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content_type', 'record_id', 'sections']);
    }

    public function test_preview_draft_404_for_layout_of_another_kind(): void
    {
        $header = app(ChromeLayoutService::class)->create(ChromeLayout::KIND_HEADER, ['name' => 'Header']);

        $this->withHeaders($this->bearer($this->admin()))
            ->postJson("/api/appearance/singles/{$header->id}/preview-as", [
                'content_type' => 'project',
                'record_id' => 1,
                'sections' => [],
            ])
            ->assertNotFound();
    }

    public function test_preview_draft_forbidden_for_editor(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('editor');
        $layout = $this->single();

        $this->withHeaders($this->bearer($editor))
            ->postJson("/api/appearance/singles/{$layout->id}/preview-as", [
                'content_type' => 'project',
                'record_id' => 1,
                'sections' => [],
            ])
            ->assertForbidden();
    }
}
