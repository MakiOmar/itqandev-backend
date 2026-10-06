<?php

namespace Tests\Feature;

use App\Models\BuilderTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BuilderTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function headingBlock(string $id = 'blk_heading1'): array
    {
        return [
            'id' => $id,
            'kind' => 'widget',
            'type' => 'heading',
            'settings' => ['text' => 'Hello'],
            'unexpected' => 'dropped',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function band(): array
    {
        return [
            'id' => 'band_one',
            'type' => 'layout',
            'layout_width' => 'full',
            'rows' => [[
                'id' => 'row_one',
                'columns' => [[
                    'id' => 'col_one',
                    'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                    'blocks' => [$this->headingBlock()],
                ]],
            ]],
        ];
    }

    private function makeTemplate(string $name = 'Hero heading', string $kind = 'block'): BuilderTemplate
    {
        return BuilderTemplate::query()->create([
            'name' => $name,
            'kind' => $kind,
            'document' => $kind === 'block' ? $this->headingBlock() : $this->band(),
        ]);
    }

    public function test_admin_saves_a_block_template_normalized_like_a_page_block(): void
    {
        $user = $this->actingAsRole('admin');

        $res = $this->postJson('/api/appearance/templates', [
            'name' => '  Hero heading ',
            'kind' => 'block',
            'document' => $this->headingBlock(),
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Hero heading')
            ->assertJsonPath('data.kind', 'block')
            ->assertJsonPath('data.block_type', 'heading')
            ->assertJsonPath('data.document.type', 'heading')
            ->assertJsonPath('data.document.id', 'blk_heading1');

        $this->assertArrayNotHasKey('unexpected', $res->json('data.document'));
        $this->assertSame($user->id, BuilderTemplate::query()->first()->created_by);
    }

    public function test_admin_saves_band_row_and_column_templates(): void
    {
        $this->actingAsRole('admin');
        $band = $this->band();
        $row = $band['rows'][0];
        $column = $row['columns'][0];

        $this->postJson('/api/appearance/templates', ['name' => 'Band', 'kind' => 'band', 'document' => $band])
            ->assertCreated()
            ->assertJsonPath('data.document.layout_width', 'full')
            ->assertJsonPath('data.document.rows.0.columns.0.blocks.0.type', 'heading')
            ->assertJsonPath('data.block_type', null);
        $this->postJson('/api/appearance/templates', ['name' => 'Row', 'kind' => 'row', 'document' => $row])
            ->assertCreated()
            ->assertJsonPath('data.document.columns.0.id', 'col_one');
        $this->postJson('/api/appearance/templates', ['name' => 'Column', 'kind' => 'column', 'document' => $column])
            ->assertCreated()
            ->assertJsonPath('data.document.blocks.0.id', 'blk_heading1');
    }

    public function test_store_rejects_unknown_kind_and_invalid_content(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/appearance/templates', ['name' => 'X', 'kind' => 'page', 'document' => $this->band()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kind');
        $this->postJson('/api/appearance/templates', [
            'name' => 'X',
            'kind' => 'block',
            'document' => ['type' => 'no_such_widget'],
        ])->assertUnprocessable()->assertJsonValidationErrors('document');
        $this->postJson('/api/appearance/templates', ['kind' => 'block', 'document' => $this->headingBlock()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_index_lists_without_documents_and_filters_by_kind(): void
    {
        $this->actingAsRole('admin');
        $this->makeTemplate('B block', 'block');
        $this->makeTemplate('A band', 'band');

        $this->getJson('/api/appearance/templates')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'A band')
            ->assertJsonMissingPath('data.0.document');
        $this->getJson('/api/appearance/templates?kind=block')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.block_type', 'heading');
    }

    public function test_show_rename_and_soft_delete(): void
    {
        $this->actingAsRole('admin');
        $template = $this->makeTemplate();

        $this->getJson("/api/appearance/templates/{$template->id}")
            ->assertOk()
            ->assertJsonPath('data.document.type', 'heading');
        $this->putJson("/api/appearance/templates/{$template->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.document.type', 'heading');
        $this->deleteJson("/api/appearance/templates/{$template->id}")->assertNoContent();

        $this->assertSoftDeleted($template);
        $this->getJson("/api/appearance/templates/{$template->id}")->assertNotFound();
    }

    public function test_bulk_delete_removes_only_selected_templates(): void
    {
        $this->actingAsRole('admin');
        $a = $this->makeTemplate('A');
        $b = $this->makeTemplate('B');
        $kept = $this->makeTemplate('C');

        $this->postJson('/api/appearance/templates/bulk-delete', ['ids' => [$a->id, $b->id]])
            ->assertOk()
            ->assertJsonPath('deleted', 2);

        $this->assertSoftDeleted($a);
        $this->assertSoftDeleted($b);
        $this->assertNotSoftDeleted($kept);
    }

    public function test_bulk_delete_validates_ids(): void
    {
        $this->actingAsRole('admin');
        $deleted = $this->makeTemplate();
        $deleted->delete();

        $this->postJson('/api/appearance/templates/bulk-delete', ['ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids');
        $this->postJson('/api/appearance/templates/bulk-delete', ['ids' => [$deleted->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids.0');
    }

    public function test_non_admins_are_forbidden(): void
    {
        $template = $this->makeTemplate();
        $this->actingAsRole('editor');

        $this->getJson('/api/appearance/templates')->assertForbidden();
        $this->getJson("/api/appearance/templates/{$template->id}")->assertForbidden();
        $this->postJson('/api/appearance/templates', ['name' => 'X', 'kind' => 'block', 'document' => $this->headingBlock()])
            ->assertForbidden();
        $this->putJson("/api/appearance/templates/{$template->id}", ['name' => 'X'])->assertForbidden();
        $this->deleteJson("/api/appearance/templates/{$template->id}")->assertForbidden();
        $this->postJson('/api/appearance/templates/bulk-delete', ['ids' => [$template->id]])->assertForbidden();
        $this->assertNotSoftDeleted($template);
    }

    public function test_guests_are_unauthenticated(): void
    {
        $this->getJson('/api/appearance/templates')->assertUnauthorized();
    }
}
