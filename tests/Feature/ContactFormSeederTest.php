<?php

namespace Tests\Feature;

use App\Models\Form;
use Database\Seeders\ContactFormSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactFormSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_presents_arabic_field_labels(): void
    {
        $this->seed(ContactFormSeeder::class);

        $form = Form::query()->where('slug', 'contact')->first();
        $this->assertNotNull($form);
        $this->assertTrue($form->translations->contains(fn ($t) => $t->locale === 'ar'));

        $ar = $this->getJson('/api/public/forms/contact', ['X-Content-Locale' => 'ar']);
        $ar->assertOk();
        $ar->assertJsonPath('settings.submit_label', 'إرسال الرسالة');

        $labels = collect($ar->json('layout.rows') ?? [])
            ->flatMap(fn ($row) => $row['fields'] ?? [])
            ->map(fn ($field) => $field['settings']['label'] ?? null)
            ->filter()
            ->values()
            ->all();

        $this->assertContains('الاسم', $labels);
        $this->assertContains('البريد الإلكتروني', $labels);
        $this->assertContains('الرسالة', $labels);

        // Idempotent
        $this->seed(ContactFormSeeder::class);
        $this->assertSame(1, Form::query()->where('slug', 'contact')->count());
    }
}
