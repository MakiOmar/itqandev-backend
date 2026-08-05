<?php

namespace App\Services\Appearance;

/**
 * Default professional Pricing CMS page layout (page builder document).
 * Used by {@see \Database\Seeders\PricingPageSeeder} and as the canonical starter shape.
 * Primary (English) copy lives on flat settings; Arabic under settings.translations.ar.
 */
final class PricingPageLayout
{
    /** Bump when seeded layout shape should replace older pricing pages missing this marker. */
    public const LAYOUT_REVISION = 1;

    /**
     * @return list<array<string, mixed>>
     */
    public static function sections(): array
    {
        return PageLayoutDocument::normalizeSectionsForPages([
            [
                'id' => 'band_pricing_header',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [
                    'layout_revision' => self::LAYOUT_REVISION,
                ],
                'rows' => [
                    [
                        'id' => 'row_pricing_header',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_pricing_header',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_pricing_page_header',
                                        'kind' => 'kit',
                                        'type' => 'page_header',
                                        'enabled' => true,
                                        'settings' => [
                                            'show_breadcrumbs' => true,
                                            'show_title' => true,
                                            'home_label' => 'Home',
                                            'eyebrow' => 'Pricing',
                                            'title_override' => 'Simple, transparent packages',
                                            'subtitle' => 'Transparent packages. Custom quotes for larger scope.',
                                            'extra_crumbs' => [],
                                            'translations' => [
                                                'ar' => [
                                                    'home_label' => 'الرئيسية',
                                                    'eyebrow' => 'الأسعار',
                                                    'title_override' => 'باقات واضحة وشفافة',
                                                    'subtitle' => 'باقات شفافة. عروض مخصّصة للنطاق الأكبر.',
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
                'id' => 'band_pricing_tiers',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_pricing_tiers',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_pricing_tiers',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_pricing',
                                        'kind' => 'kit',
                                        'type' => 'pricing',
                                        'enabled' => true,
                                        'settings' => [
                                            // Page header carries title; tiers match KitRegistry defaults.
                                            'title' => '',
                                            'subtitle' => '',
                                            'tiers' => [
                                                [
                                                    'name' => 'Starter',
                                                    'price' => '$2,500',
                                                    'period' => 'project',
                                                    'description' => 'Small sites and MVPs.',
                                                    'features' => "Discovery workshop\nResponsive build\n2 revision rounds",
                                                    'cta' => 'Get started',
                                                    'highlighted' => false,
                                                ],
                                                [
                                                    'name' => 'Growth',
                                                    'price' => '$7,500',
                                                    'period' => 'project',
                                                    'description' => 'Full product surface.',
                                                    'features' => "UX + UI\nIntegrations\nPerformance pass\nLaunch support",
                                                    'cta' => 'Talk to us',
                                                    'highlighted' => true,
                                                ],
                                            ],
                                            'translations' => [
                                                'ar' => [
                                                    'tiers' => [
                                                        [
                                                            'name' => 'ابدأ',
                                                            'price' => '$2,500',
                                                            'period' => 'مشروع',
                                                            'description' => 'مواقع صغيرة ونماذج أولية.',
                                                            'features' => "ورشة استكشاف\nبناء متجاوب\nجولتا تعديلات",
                                                            'cta' => 'ابدأ الآن',
                                                            'highlighted' => false,
                                                        ],
                                                        [
                                                            'name' => 'نمو',
                                                            'price' => '$7,500',
                                                            'period' => 'مشروع',
                                                            'description' => 'سطح منتج كامل.',
                                                            'features' => "تجربة وواجهة\nتكاملات\nمرور أداء\nدعم الإطلاق",
                                                            'cta' => 'تحدث معنا',
                                                            'highlighted' => true,
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
            [
                'id' => 'band_pricing_faq',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_pricing_faq',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_pricing_faq',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_pricing_faq',
                                        'kind' => 'kit',
                                        'type' => 'faq',
                                        'enabled' => true,
                                        'settings' => [
                                            'title' => 'Frequently asked questions',
                                            'items' => [
                                                [
                                                    'question' => "What's included?",
                                                    'answer' => 'Scope is confirmed in a short discovery call. Packages cover the listed deliverables; extras are quoted separately.',
                                                ],
                                                [
                                                    'question' => 'Do you do retainers?',
                                                    'answer' => 'Yes — ongoing maintenance and product iteration retainers are available. Ask for a custom quote.',
                                                ],
                                                [
                                                    'question' => 'Can we mix packages?',
                                                    'answer' => 'Absolutely. Many engagements start with Starter discovery then expand into a Growth build.',
                                                ],
                                            ],
                                            'translations' => [
                                                'ar' => [
                                                    'title' => 'الأسئلة الشائعة',
                                                    'items' => [
                                                        [
                                                            'question' => 'ماذا يشمل السعر؟',
                                                            'answer' => 'يُؤكَّد النطاق في مكالمة استكشاف قصيرة. تغطي الباقات المخرجات المدرجة؛ وما زاد يُسعَّر على حدة.',
                                                        ],
                                                        [
                                                            'question' => 'هل تقدّمون عقود احتفاظ؟',
                                                            'answer' => 'نعم — عقود صيانة وتكرار منتج مستمرة متاحة. اطلبوا عرضًا مخصّصًا.',
                                                        ],
                                                        [
                                                            'question' => 'هل يمكن الجمع بين الباقات؟',
                                                            'answer' => 'بالتأكيد. كثير من الانخراطات تبدأ باستكشاف «ابدأ» ثم تتوسع إلى بناء «نمو».',
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
