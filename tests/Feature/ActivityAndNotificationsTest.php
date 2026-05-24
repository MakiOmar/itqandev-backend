<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Notifications\ContactSubmissionReceived;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    public function test_activity_list_requires_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->withHeaders($this->bearerHeaders($viewer))
            ->getJson('/api/v1/activity')
            ->assertForbidden();

        $superAdmin = User::query()->where('email', 'superadmin@credocode.test')->first();
        $this->assertNotNull($superAdmin);

        ActivityLog::query()->create([
            'user_id' => $superAdmin->id,
            'action' => 'test.action',
            'created_at' => now(),
        ]);

        Sanctum::actingAs($superAdmin, ['*']);
        $this->getJson('/api/v1/activity')
            ->assertOk();
    }

    public function test_notifications_mark_read(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $admin->notify(new ContactSubmissionReceived([
            'name' => 'Test',
            'email' => 't@example.com',
            'subject' => 'Hi',
            'message' => 'Body',
        ]));

        $row = $admin->notifications()->first();
        $this->assertNotNull($row);

        $this->withHeaders($this->bearerHeaders($admin))
            ->postJson('/api/v1/notifications/'.$row->id.'/read')
            ->assertOk();

        $this->assertNotNull($row->fresh()->read_at);
    }
}
