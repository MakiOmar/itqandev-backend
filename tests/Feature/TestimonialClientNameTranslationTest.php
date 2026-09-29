<?php

namespace Tests\Feature;

use App\Models\Testimonial;
use App\Models\User;
use App\Support\ProjectSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TestimonialClientNameTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ProjectSettingsStore::save([
            'default_locale' => 'ar',
            'site_languages' => [
                ['code' => 'ar', 'label' => 'Arabic', 'native_label' => 'Arabic', 'rtl' => true],
                ['code' => 'en', 'label' => 'English', 'native_label' => 'English', 'rtl' => false],
            ],
        ]);
    }

    private function actingAdmin(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create();
        $user->assignRole('admin');
        Sanctum::actingAs($user);
    }

    private function makeTestimonial(): Testimonial
    {
        return Testimonial::create([
            'content_locale' => 'ar',
            'client_name' => 'Ahmad AR',
            'rating' => 5,
            'content' => 'Arabic quote',
            'approved' => true,
        ]);
    }

    /** Admin form always resends the primary columns alongside translations. */
    private function primaryPayload(): array
    {
        return [
            'content_locale' => 'ar',
            'client_name' => 'Ahmad AR',
            'rating' => 5,
            'content' => 'Arabic quote',
            'approved' => true,
        ];
    }

    public function test_translated_client_name_is_stored_and_presented_per_locale(): void
    {
        $this->actingAdmin();
        $t = $this->makeTestimonial();

        $this->putJson('/api/v1/testimonials/'.$t->id, $this->primaryPayload() + [
            'translations' => [
                ['locale' => 'en', 'client_name' => 'Ahmed Al-Otaibi', 'content' => 'English quote'],
            ],
        ])->assertOk()->assertJsonPath('translations.0.client_name', 'Ahmed Al-Otaibi');

        $this->assertSame('Ahmad AR', $t->fresh()->client_name);

        $this->getJson('/api/public/testimonials', ['X-Content-Locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.0.authorName', 'Ahmed Al-Otaibi');

        $this->getJson('/api/public/testimonials', ['X-Content-Locale' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.0.authorName', 'Ahmad AR');
    }

    public function test_empty_translated_client_name_falls_back_to_primary(): void
    {
        $this->actingAdmin();
        $t = $this->makeTestimonial();

        $this->putJson('/api/v1/testimonials/'.$t->id, $this->primaryPayload() + [
            'translations' => [
                ['locale' => 'en', 'client_name' => '', 'content' => 'English quote'],
            ],
        ])->assertOk();

        $this->getJson('/api/v1/testimonials/'.$t->id, ['X-Content-Locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('client_name', 'Ahmad AR')
            ->assertJsonPath('content', 'English quote');
    }
}
