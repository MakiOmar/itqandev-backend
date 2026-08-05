<?php

namespace App\Services\Appearance;

/**
 * Default professional Services CMS page layout (page builder document).
 * Used by {@see \Database\Seeders\ServicesPageSeeder} and as the canonical starter shape.
 * Primary (English) copy lives on flat settings; Arabic under settings.translations.ar.
 */
final class ServicesPageLayout
{
    /** Bump when seeded layout shape should replace older services pages missing this marker. */
    public const LAYOUT_REVISION = 1;

    /**
     * @return list<array<string, mixed>>
     */
    public static function sections(): array
    {
        return PageLayoutDocument::normalizeSectionsForPages([
            [
                'id' => 'band_services_header',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [
                    'layout_revision' => self::LAYOUT_REVISION,
                ],
                'rows' => [
                    [
                        'id' => 'row_services_header',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_services_header',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_services_page_header',
                                        'kind' => 'kit',
                                        'type' => 'page_header',
                                        'enabled' => true,
                                        'settings' => [
                                            'show_breadcrumbs' => true,
                                            'show_title' => true,
                                            'home_label' => 'Home',
                                            'eyebrow' => 'Services',
                                            'title_override' => 'Our services',
                                            'subtitle' => 'Full product lifecycle — discovery, design, engineering, and launch support for web and mobile.',
                                            'extra_crumbs' => [],
                                            'translations' => [
                                                'ar' => [
                                                    'home_label' => 'الرئيسية',
                                                    'eyebrow' => 'الخدمات',
                                                    'title_override' => 'خدماتنا',
                                                    'subtitle' => 'دورة حياة المنتج كاملة — استكشاف وتصميم وهندسة ودعم الإطلاق للويب والموبايل.',
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
                'id' => 'band_services_teaser',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_services_teaser',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_services_teaser',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_services_teaser',
                                        'kind' => 'kit',
                                        'type' => 'services_teaser',
                                        'enabled' => true,
                                        'settings' => [
                                            // Page header carries title; keep kit headings empty.
                                            'eyebrow' => '',
                                            'title' => '',
                                            'subtitle' => '',
                                            'limit' => 24,
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
