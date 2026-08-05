<?php

namespace App\Services\Appearance;

/**
 * Default professional Contact CMS page layout (page builder document).
 * Used by {@see \Database\Seeders\ContactPageSeeder} and as the canonical starter shape.
 * Primary (English) copy lives on flat settings; Arabic under settings.translations.ar.
 */
final class ContactPageLayout
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function sections(): array
    {
        return PageLayoutDocument::normalizeSectionsForPages([
            [
                'id' => 'band_contact_header',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_contact_header',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_contact_header',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_contact_page_header',
                                        'kind' => 'kit',
                                        'type' => 'page_header',
                                        'enabled' => true,
                                        'settings' => [
                                            'show_breadcrumbs' => true,
                                            'show_title' => true,
                                            'home_label' => 'Home',
                                            'title_override' => 'Get in touch',
                                            'subtitle' => "Tell us about your project. We'll respond within 24 hours.",
                                            'extra_crumbs' => [],
                                            'translations' => [
                                                'ar' => [
                                                    'home_label' => 'الرئيسية',
                                                    'title_override' => 'تواصل معنا',
                                                    'subtitle' => 'أخبرنا عن مشروعك. نرد خلال ٢٤ ساعة.',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'band_contact_main',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_contact_main',
                        'stack_below' => 'tablet',
                        'gap' => 8,
                        'columns' => [
                            [
                                'id' => 'col_contact_form',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 7],
                                'blocks' => [
                                    [
                                        'id' => 'kit_contact_form',
                                        'kind' => 'kit',
                                        'type' => 'form',
                                        'enabled' => true,
                                        'settings' => [
                                            'form_slug' => 'contact',
                                            'title' => 'Send a message',
                                            'subtitle' => 'Share a few details and we will follow up by email.',
                                            'translations' => [
                                                'ar' => [
                                                    'title' => 'أرسل رسالة',
                                                    'subtitle' => 'شارك بعض التفاصيل وسنتابع معك عبر البريد الإلكتروني.',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'id' => 'col_contact_info',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 5],
                                'blocks' => [
                                    [
                                        'id' => 'kit_contact_info',
                                        'kind' => 'kit',
                                        'type' => 'contact_info',
                                        'enabled' => true,
                                        'settings' => [
                                            'office_heading' => 'Office',
                                            'address' => '',
                                            'email' => '',
                                            'phone' => '',
                                            'calendar_link' => '',
                                            'calendar_label' => 'Book a call',
                                            'use_site_contact' => true,
                                            'socials' => [],
                                            'translations' => [
                                                'ar' => [
                                                    'office_heading' => 'المكتب',
                                                    'calendar_label' => 'احجز مكالمة',
                                                ],
                                            ],
                                        ],
                                    ],
                                    [
                                        'id' => 'widget_contact_response',
                                        'kind' => 'widget',
                                        'type' => 'badge',
                                        'enabled' => true,
                                        'settings' => [
                                            'text' => 'Typical reply: within 24 hours',
                                            'translations' => [
                                                'ar' => [
                                                    'text' => 'الرد المعتاد: خلال ٢٤ ساعة',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'band_contact_map',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_contact_map',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_contact_map',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_contact_map_heading',
                                        'kind' => 'widget',
                                        'type' => 'heading',
                                        'enabled' => true,
                                        'settings' => [
                                            'text' => 'Visit us',
                                            'level' => 'h2',
                                            'align' => 'start',
                                            'translations' => [
                                                'ar' => [
                                                    'text' => 'زرنا',
                                                ],
                                            ],
                                        ],
                                    ],
                                    [
                                        'id' => 'widget_contact_map',
                                        'kind' => 'widget',
                                        'type' => 'map',
                                        'enabled' => true,
                                        'settings' => [
                                            // Operator: paste Google Maps iframe src in Page Builder.
                                            'embed_url' => '',
                                            'height' => 360,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'band_contact_faq',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_contact_faq',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_contact_faq',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_contact_faq',
                                        'kind' => 'kit',
                                        'type' => 'faq',
                                        'enabled' => true,
                                        'settings' => [
                                            'title' => 'Before you write',
                                            'items' => [
                                                [
                                                    'question' => 'How quickly do you reply?',
                                                    'answer' => 'We aim to respond within one business day. Urgent launches can be flagged in your message subject.',
                                                ],
                                                [
                                                    'question' => 'What should I include?',
                                                    'answer' => 'Goals, timeline, budget range, and any links (briefs, repos, or references) help us reply with a concrete next step.',
                                                ],
                                                [
                                                    'question' => 'Do you take discovery calls?',
                                                    'answer' => 'Yes — use the calendar link on this page or ask for a slot in your message.',
                                                ],
                                            ],
                                            'translations' => [
                                                'ar' => [
                                                    'title' => 'قبل أن تكتب',
                                                    'items' => [
                                                        [
                                                            'question' => 'ما سرعة الرد؟',
                                                            'answer' => 'نهدف للرد خلال يوم عمل واحد. يمكنكم الإشارة إلى الإطلاقات العاجلة في عنوان الرسالة.',
                                                        ],
                                                        [
                                                            'question' => 'ماذا أضمّن في الرسالة؟',
                                                            'answer' => 'الأهداف والجدول الزمني ونطاق الميزانية وأي روابط (ملخصات أو مستودعات أو مراجع) تساعدنا على الرد بخطوة تالية واضحة.',
                                                        ],
                                                        [
                                                            'question' => 'هل تقدّمون مكالمات استكشافية؟',
                                                            'answer' => 'نعم — استخدموا رابط التقويم في هذه الصفحة أو اطلبوا موعداً في رسالتكم.',
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
