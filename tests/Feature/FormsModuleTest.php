<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FormsModuleTest extends TestCase
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

    public function test_admin_can_create_and_list_forms(): void
    {
        Sanctum::actingAs($this->admin());

        $create = $this->postJson('/api/v1/forms', [
            'title' => 'Contact',
            'slug' => 'contact',
            'status' => 'published',
        ]);
        $create->assertCreated();
        $create->assertJsonPath('slug', 'contact');
        $this->assertNotEmpty($create->json('layout.rows'));
        $this->assertNotEmpty($create->json('actions'));

        $this->getJson('/api/v1/forms')->assertOk()->assertJsonFragment(['slug' => 'contact']);
    }

    public function test_public_can_fetch_and_submit_published_form(): void
    {
        Sanctum::actingAs($this->admin());
        $form = $this->postJson('/api/v1/forms', [
            'title' => 'Contact',
            'slug' => 'contact',
            'status' => 'published',
            'actions' => [
                [
                    'id' => 'store_1',
                    'type' => 'store_submission',
                    'enabled' => true,
                    'settings' => ['store_ip' => true],
                ],
            ],
        ])->json();

        $fields = [];
        foreach ($form['layout']['rows'] as $row) {
            foreach ($row['fields'] as $field) {
                $fields[$field['id']] = match ($field['type']) {
                    'email' => 'user@example.com',
                    'textarea' => 'Hello there',
                    default => 'Jane Doe',
                };
            }
        }

        $this->getJson('/api/public/forms/contact')->assertOk()->assertJsonPath('slug', 'contact');

        $submit = $this->postJson('/api/public/forms/contact/submit', $fields);
        $submit->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseCount('form_submissions', 1);
    }

    public function test_features_include_forms_module(): void
    {
        $this->assertTrue(\App\Support\FeatureModules::enabled('forms'));
        $this->assertArrayHasKey('forms', \App\Support\FeatureModules::all());
    }
}
