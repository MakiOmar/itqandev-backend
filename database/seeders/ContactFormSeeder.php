<?php

namespace Database\Seeders;

use App\Models\Form;
use App\Services\Forms\FormLayoutDocument;
use Illuminate\Database\Seeder;

/**
 * Ensures a published "contact" form exists for /contact and embeds.
 */
class ContactFormSeeder extends Seeder
{
    public function run(): void
    {
        if (Form::query()->where('slug', 'contact')->exists()) {
            return;
        }

        Form::create([
            'title' => 'Contact',
            'slug' => 'contact',
            'status' => Form::STATUS_PUBLISHED,
            'published_at' => now(),
            'content_locale' => null,
            'layout' => FormLayoutDocument::normalizeLayout([]),
            'actions' => FormLayoutDocument::normalizeActions([
                [
                    'id' => 'store_submission_default',
                    'type' => 'store_submission',
                    'enabled' => true,
                    'settings' => ['store_ip' => true],
                ],
                [
                    'id' => 'email_admins_default',
                    'type' => 'email_admins',
                    'enabled' => true,
                    'settings' => [
                        'recipients' => '',
                        'subject' => 'New contact: {{form_title}}',
                        'notify_database' => true,
                    ],
                ],
            ]),
            'settings' => FormLayoutDocument::normalizeSettings([
                'submit_label' => 'Send message',
                'success_message' => 'Thank you. We will get back to you soon.',
                'honeypot' => true,
                'captcha' => 'none',
            ]),
        ]);
    }
}
