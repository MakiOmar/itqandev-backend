<?php

namespace Database\Seeders;

use App\Models\Form;
use App\Services\Forms\FormLayoutDocument;
use Illuminate\Database\Seeder;

/**
 * Ensures a published "contact" form exists for /contact and embeds,
 * with Arabic field labels + submit copy under settings/field translations.
 */
class ContactFormSeeder extends Seeder
{
    public function run(): void
    {
        $form = Form::query()->where('slug', 'contact')->first();

        if ($form === null) {
            $form = Form::create([
                'title' => 'Contact',
                'slug' => 'contact',
                'status' => Form::STATUS_PUBLISHED,
                'published_at' => now(),
                'content_locale' => null,
                'layout' => $this->defaultLayoutWithArabic(),
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
                    'error_message' => 'Something went wrong. Please try again.',
                    'honeypot' => true,
                    'captcha' => 'none',
                    'translations' => [
                        'ar' => [
                            'submit_label' => 'إرسال الرسالة',
                            'success_message' => 'شكراً لك. سنتواصل معك قريباً.',
                            'error_message' => 'حدث خطأ. يرجى المحاولة مرة أخرى.',
                        ],
                    ],
                ]),
            ]);
        } else {
            $form->layout = $this->ensureArabicFieldTranslations(
                is_array($form->layout) ? $form->layout : []
            );
            $form->settings = $this->ensureArabicSettingsTranslations(
                is_array($form->settings) ? $form->settings : []
            );
            $form->save();
        }

        $form->translations()->updateOrCreate(
            ['locale' => 'ar'],
            ['title' => 'تواصل']
        );

        Form::bumpPublicCacheVersion();
    }

    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    private function defaultLayoutWithArabic(): array
    {
        return FormLayoutDocument::normalizeLayout([
            'rows' => [
                [
                    'id' => 'row_contact_identity',
                    'fields' => [
                        [
                            'id' => 'field_name',
                            'type' => 'text',
                            'span' => ['mobile' => 12, 'tablet' => 4, 'desktop' => 4],
                            'settings' => [
                                'label' => 'Name',
                                'name' => 'name',
                                'required' => true,
                                'translations' => [
                                    'ar' => ['label' => 'الاسم'],
                                ],
                            ],
                        ],
                        [
                            'id' => 'field_email',
                            'type' => 'email',
                            'span' => ['mobile' => 12, 'tablet' => 4, 'desktop' => 4],
                            'settings' => [
                                'label' => 'Email',
                                'name' => 'email',
                                'required' => true,
                                'translations' => [
                                    'ar' => ['label' => 'البريد الإلكتروني'],
                                ],
                            ],
                        ],
                        [
                            'id' => 'field_phone',
                            'type' => 'tel',
                            'span' => ['mobile' => 12, 'tablet' => 4, 'desktop' => 4],
                            'settings' => [
                                'label' => 'Phone',
                                'name' => 'phone',
                                'required' => false,
                                'translations' => [
                                    'ar' => ['label' => 'الهاتف'],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'row_contact_message',
                    'fields' => [
                        [
                            'id' => 'field_message',
                            'type' => 'textarea',
                            'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                            'settings' => [
                                'label' => 'Message',
                                'name' => 'message',
                                'required' => true,
                                'rows' => 5,
                                'translations' => [
                                    'ar' => ['label' => 'الرسالة'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $layout
     * @return array{rows: list<array<string, mixed>>}
     */
    private function ensureArabicFieldTranslations(array $layout): array
    {
        $normalized = FormLayoutDocument::normalizeLayout($layout);
        $byName = [
            'name' => 'الاسم',
            'email' => 'البريد الإلكتروني',
            'phone' => 'الهاتف',
            'tel' => 'الهاتف',
            'message' => 'الرسالة',
        ];
        $byEnglishLabel = [
            'name' => 'الاسم',
            'email' => 'البريد الإلكتروني',
            'phone' => 'الهاتف',
            'message' => 'الرسالة',
        ];

        foreach ($normalized['rows'] as &$row) {
            foreach ($row['fields'] as &$field) {
                $settings = is_array($field['settings'] ?? null) ? $field['settings'] : [];
                $name = strtolower(trim((string) ($settings['name'] ?? '')));
                $label = strtolower(trim((string) ($settings['label'] ?? '')));
                $arLabel = $byName[$name] ?? $byEnglishLabel[$label] ?? null;
                if ($arLabel === null) {
                    continue;
                }
                $translations = is_array($settings['translations'] ?? null) ? $settings['translations'] : [];
                $arBag = is_array($translations['ar'] ?? null) ? $translations['ar'] : [];
                if (trim((string) ($arBag['label'] ?? '')) === '') {
                    $arBag['label'] = $arLabel;
                }
                $translations['ar'] = $arBag;
                $settings['translations'] = $translations;
                $field['settings'] = $settings;
            }
            unset($field);
        }
        unset($row);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function ensureArabicSettingsTranslations(array $settings): array
    {
        $normalized = FormLayoutDocument::normalizeSettings($settings);
        $translations = is_array($normalized['translations'] ?? null) ? $normalized['translations'] : [];
        $ar = is_array($translations['ar'] ?? null) ? $translations['ar'] : [];
        $ar['submit_label'] = trim((string) ($ar['submit_label'] ?? '')) !== ''
            ? $ar['submit_label']
            : 'إرسال الرسالة';
        $ar['success_message'] = trim((string) ($ar['success_message'] ?? '')) !== ''
            ? $ar['success_message']
            : 'شكراً لك. سنتواصل معك قريباً.';
        $ar['error_message'] = trim((string) ($ar['error_message'] ?? '')) !== ''
            ? $ar['error_message']
            : 'حدث خطأ. يرجى المحاولة مرة أخرى.';
        $translations['ar'] = $ar;
        $normalized['translations'] = $translations;

        return $normalized;
    }
}
