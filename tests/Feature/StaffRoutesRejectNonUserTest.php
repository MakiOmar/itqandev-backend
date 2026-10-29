<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Addons may authenticate their own principals (e.g. customers) with Sanctum tokens.
 * Staff routes must refuse them instead of reaching policies that expect App\Models\User.
 */
class StaffRoutesRejectNonUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_user_principal_is_forbidden_on_staff_routes(): void
    {
        $this->actingAs(new GenericUser(['id' => 1, 'email' => 'buyer@example.com']), 'sanctum');

        $this->getJson('/api/me')->assertForbidden();
        $this->getJson('/api/settings')->assertForbidden();
    }
}
