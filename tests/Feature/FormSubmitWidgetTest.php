<?php

namespace Tests\Feature;

use App\Models\FormSubmission;
use App\Models\User;
use App\Services\Forms\FormFieldRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FormSubmitWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Permission::findOrCreate('manage forms');
        $role = Role::findOrCreate('admin');
        $role->givePermissionTo('manage forms');
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $submitSettings
     * @return array<string, mixed>
     */
    private function createFormWithSubmit(array $submitSettings): array
    {
        Sanctum::actingAs($this->admin());

        return $this->postJson('/api/v1/forms', [
            'title' => 'Contact',
            'slug' => 'contact',
            'status' => 'published',
            'layout' => [
                'rows' => [
                    ['id' => 'r1', 'fields' => [
                        ['id' => 'f_name', 'type' => 'text', 'span' => 12, 'settings' => ['label' => 'Name', 'required' => true]],
                    ]],
                    ['id' => 'r2', 'fields' => [
                        [
                            'id' => 'f_submit',
                            'type' => 'submit',
                            'span' => 12,
                            'settings' => $submitSettings,
                            'styles' => ['desktop' => ['btn_primary_bg' => '#ff0000']],
                        ],
                    ]],
                ],
            ],
            'actions' => [
                ['id' => 'store_1', 'type' => 'store_submission', 'enabled' => true, 'settings' => []],
            ],
        ])->assertCreated()->json();
    }

    public function test_submit_type_is_offered_in_the_palette(): void
    {
        $types = array_column(FormFieldRegistry::forAdmin(), 'type');

        $this->assertContains('submit', $types);
        $this->assertFalse(FormFieldRegistry::collectsInput('submit'));
        $this->assertTrue(FormFieldRegistry::collectsInput('text'));
    }

    public function test_layout_keeps_submit_widget_and_sanitizes_alignment(): void
    {
        $form = $this->createFormWithSubmit(['label' => 'Send', 'align' => 'sideways']);

        $submit = $form['layout']['rows'][1]['fields'][0];
        $this->assertSame('submit', $submit['type']);
        $this->assertSame('Send', $submit['settings']['label']);
        $this->assertSame('start', $submit['settings']['align']);
        $this->assertSame('#ff0000', $submit['styles']['desktop']['btn_primary_bg']);

        $this->getJson('/api/public/forms/contact')
            ->assertOk()
            ->assertJsonPath('layout.rows.1.fields.0.type', 'submit')
            ->assertJsonPath('layout.rows.1.fields.0.settings.label', 'Send');
    }

    public function test_submit_widget_is_not_validated_or_stored(): void
    {
        $this->createFormWithSubmit(['label' => 'Send', 'align' => 'full']);

        $this->postJson('/api/public/forms/contact/submit', ['f_name' => 'Jane'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $payload = FormSubmission::query()->firstOrFail()->payload;
        $encoded = json_encode($payload);
        $this->assertStringContainsString('Jane', (string) $encoded);
        $this->assertStringNotContainsString('f_submit', (string) $encoded);
    }
}
