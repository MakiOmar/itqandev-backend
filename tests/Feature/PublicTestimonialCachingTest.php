<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectTranslation;
use App\Models\Testimonial;
use App\Models\TestimonialTranslation;
use App\Models\User;
use App\Support\ProjectSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicTestimonialCachingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ProjectSettingsStore::save([
            'default_locale' => 'en',
            'site_languages' => [
                ['code' => 'en', 'label' => 'English', 'native_label' => 'English', 'rtl' => false],
                ['code' => 'ar', 'label' => 'Arabic', 'native_label' => 'العربية', 'rtl' => true],
            ],
        ]);
    }

    public function test_empty_translation_rows_do_not_count_as_locale_content(): void
    {
        $translated = $this->testimonial('Translated');
        TestimonialTranslation::query()->create(['testimonial_id' => $translated->id, 'locale' => 'ar', 'content' => 'اقتباس']);
        $empty = $this->testimonial('Empty');
        TestimonialTranslation::query()->create(['testimonial_id' => $empty->id, 'locale' => 'ar', 'content' => '']);

        $this->withHeader('X-Content-Locale', 'ar')
            ->getJson('/api/public/testimonials')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.quote', 'اقتباس');
    }

    public function test_untranslated_linked_project_title_is_dropped_not_the_testimonial(): void
    {
        $project = Project::query()->create(['title' => 'English project', 'slug' => 'english-project', 'status' => 'published']);
        ProjectTranslation::query()->create(['project_id' => $project->id, 'locale' => 'ar', 'title' => '']);
        $testimonial = $this->testimonial('Linked', $project->id);
        TestimonialTranslation::query()->create(['testimonial_id' => $testimonial->id, 'locale' => 'ar', 'content' => 'مرتبط']);

        $this->withHeader('X-Content-Locale', 'ar')
            ->getJson('/api/public/testimonials')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.projectTitle', null);
    }

    public function test_bulk_unapproval_and_delete_hide_testimonials_immediately(): void
    {
        $first = $this->testimonial('First');
        $second = $this->testimonial('Second');
        $this->getJson('/api/public/testimonials')->assertJsonCount(2, 'data');

        $this->actingAdmin();
        $this->postJson('/api/v1/testimonials/bulk-approval', ['ids' => [$first->id], 'approved' => false])->assertOk();
        $this->getJson('/api/public/testimonials')->assertJsonCount(1, 'data');

        $this->postJson('/api/v1/testimonials/bulk-delete', ['ids' => [$second->id]])->assertOk();
        $this->getJson('/api/public/testimonials')->assertJsonCount(0, 'data');
    }

    public function test_project_rename_refreshes_linked_testimonials(): void
    {
        $project = Project::query()->create(['title' => 'Old name', 'slug' => 'renamed', 'status' => 'published']);
        $this->testimonial('Linked', $project->id);
        $this->getJson('/api/public/testimonials')->assertJsonPath('data.0.projectTitle', 'Old name');

        $project->update(['title' => 'New name']);

        $this->getJson('/api/public/testimonials')->assertJsonPath('data.0.projectTitle', 'New name');
    }

    private function testimonial(string $name, ?int $projectId = null): Testimonial
    {
        return Testimonial::query()->create([
            'project_id' => $projectId,
            'client_name' => $name,
            'rating' => 5,
            'content' => $name.' quote',
            'approved' => true,
        ]);
    }

    private function actingAdmin(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create();
        $user->assignRole('admin');
        Sanctum::actingAs($user);
    }
}
