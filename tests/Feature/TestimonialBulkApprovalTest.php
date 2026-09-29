<?php

namespace Tests\Feature;

use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TestimonialBulkApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): void
    {
        Role::findOrCreate($role);
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);
    }

    private function makeTestimonial(bool $approved): Testimonial
    {
        return Testimonial::create([
            'client_name' => 'Client',
            'rating' => 5,
            'content' => 'Great work',
            'approved' => $approved,
        ]);
    }

    public function test_admin_can_bulk_approve_and_unapprove(): void
    {
        $this->actingAsRole('admin');
        $a = $this->makeTestimonial(false);
        $b = $this->makeTestimonial(false);
        $untouched = $this->makeTestimonial(false);

        $this->postJson('/api/v1/testimonials/bulk-approval', ['ids' => [$a->id, $b->id], 'approved' => true])
            ->assertOk()
            ->assertJsonPath('updated', 2)
            ->assertJsonPath('approved', true);

        $this->assertTrue($a->fresh()->approved);
        $this->assertTrue($b->fresh()->approved);
        $this->assertFalse($untouched->fresh()->approved);

        $this->postJson('/api/v1/testimonials/bulk-approval', ['ids' => [$a->id], 'approved' => false])
            ->assertOk()
            ->assertJsonPath('updated', 1);

        $this->assertFalse($a->fresh()->approved);
    }

    public function test_bulk_approval_validates_payload(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/testimonials/bulk-approval', ['ids' => [], 'approved' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids']);

        $this->postJson('/api/v1/testimonials/bulk-approval', ['ids' => [999999]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids.0', 'approved']);
    }

    public function test_user_without_rights_cannot_bulk_approve(): void
    {
        $this->actingAsRole('user');
        $t = $this->makeTestimonial(false);

        $this->postJson('/api/v1/testimonials/bulk-approval', ['ids' => [$t->id], 'approved' => true])
            ->assertForbidden();

        $this->assertFalse($t->fresh()->approved);
    }
}
