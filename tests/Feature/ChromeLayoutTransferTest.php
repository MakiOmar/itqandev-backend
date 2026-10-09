<?php

namespace Tests\Feature;

use App\Models\ChromeLayout;
use App\Models\User;
use App\Services\Appearance\ChromeLayoutTransferService;
use App\Services\Appearance\FooterBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChromeLayoutTransferTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): void
    {
        Role::findOrCreate($role);
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);
    }

    private function makeFooter(string $slug, string $status = 'draft', bool $siteDefault = false): ChromeLayout
    {
        return ChromeLayout::query()->create([
            'kind' => ChromeLayout::KIND_FOOTER,
            'name' => 'Footer '.$slug,
            'slug' => $slug,
            'status' => $status,
            'document' => (new FooterBuilderService)->defaultDocument(),
            'is_site_default' => $siteDefault,
        ]);
    }

    public function test_export_returns_envelope_for_kind_and_selection(): void
    {
        $this->actingAsRole('admin');
        $a = $this->makeFooter('alpha');
        $this->makeFooter('beta');

        $this->getJson('/api/appearance/footers/export')
            ->assertOk()
            ->assertJsonPath('format', ChromeLayoutTransferService::FORMAT)
            ->assertJsonPath('kind', 'footer')
            ->assertJsonCount(ChromeLayout::query()->kind('footer')->count(), 'items');

        $this->getJson('/api/appearance/footers/export?ids[]='.$a->id)
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.slug', 'alpha')
            ->assertJsonStructure(['items' => [['name', 'slug', 'status', 'document' => ['sections']]]]);
    }

    public function test_import_creates_new_and_updates_existing_by_slug(): void
    {
        $this->actingAsRole('admin');
        $existing = $this->makeFooter('alpha', 'published');
        $envelope = $this->getJson('/api/appearance/footers/export?ids[]='.$existing->id)->json();

        $envelope['items'][0]['name'] = 'Alpha renamed';
        $envelope['items'][0]['status'] = 'draft';
        $envelope['items'][] = [...$envelope['items'][0], 'name' => 'Gamma', 'slug' => 'gamma'];

        $this->postJson('/api/appearance/footers/import', $envelope)
            ->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('skipped', 0);

        $existing->refresh();
        $this->assertSame('Alpha renamed', $existing->name);
        $this->assertSame('published', $existing->status, 'Import must not change status of existing layouts.');
        $this->assertDatabaseHas('chrome_layouts', ['kind' => 'footer', 'slug' => 'gamma', 'status' => 'draft']);
    }

    public function test_import_rejects_other_kind_or_format(): void
    {
        $this->actingAsRole('admin');
        $this->makeFooter('alpha');
        $envelope = $this->getJson('/api/appearance/footers/export')->json();

        $this->postJson('/api/appearance/headers/import', $envelope)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kind']);

        $this->postJson('/api/appearance/footers/import', [...$envelope, 'format' => 'other'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['format']);
    }

    public function test_bulk_status_and_delete_respect_guards(): void
    {
        $this->actingAsRole('admin');
        $default = $this->makeFooter('main', 'published', true);
        $a = $this->makeFooter('alpha');
        $b = $this->makeFooter('beta');

        $this->postJson('/api/appearance/footers/bulk-status', ['ids' => [$a->id, $b->id], 'status' => 'published'])
            ->assertOk()
            ->assertJsonPath('updated', 2);
        $this->assertSame('published', $a->fresh()->status);

        $this->postJson('/api/appearance/footers/bulk-delete', ['ids' => [$default->id, $a->id]])
            ->assertOk()
            ->assertJsonPath('deleted', 1)
            ->assertJsonPath('skipped', 1);
        $this->assertDatabaseHas('chrome_layouts', ['id' => $default->id]);
        $this->assertDatabaseMissing('chrome_layouts', ['id' => $a->id]);
    }

    public function test_bulk_validates_ids_against_kind(): void
    {
        $this->actingAsRole('admin');
        $footer = $this->makeFooter('alpha');

        $this->postJson('/api/appearance/headers/bulk-delete', ['ids' => [$footer->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids.0']);

        $this->postJson('/api/appearance/footers/bulk-status', ['ids' => [], 'status' => 'nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids', 'status']);
    }

    public function test_user_without_rights_is_forbidden(): void
    {
        $this->actingAsRole('user');
        $footer = $this->makeFooter('alpha');

        $this->getJson('/api/appearance/footers/export')->assertForbidden();
        $this->postJson('/api/appearance/footers/bulk-delete', ['ids' => [$footer->id]])->assertForbidden();
        $this->assertDatabaseHas('chrome_layouts', ['id' => $footer->id]);
    }
}
