<?php

namespace App\Services\Appearance;

/**
 * Default professional Work / Portfolio CMS page layout (page builder document).
 * Used by {@see \Database\Seeders\WorkPageSeeder}.
 * Primary (English) copy on flat settings; Arabic under settings.translations.ar.
 */
final class WorkPageLayout
{
    /** Bump when seeded layout shape should replace older work pages missing this marker. */
    public const LAYOUT_REVISION = 1;

    /**
     * @return list<array<string, mixed>>
     */
    public static function sections(): array
    {
        return PageLayoutDocument::normalizeSectionsForPages([
            [
                'id' => 'band_work_header',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [
                    'layout_revision' => self::LAYOUT_REVISION,
                ],
                'rows' => [
                    [
                        'id' => 'row_work_header',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_work_header',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_work_page_header',
                                        'kind' => 'kit',
                                        'type' => 'page_header',
                                        'enabled' => true,
                                        'settings' => [
                                            'show_breadcrumbs' => true,
                                            'show_title' => true,
                                            'home_label' => 'Home',
                                            'eyebrow' => 'Portfolio',
                                            'title_override' => 'Our work',
                                            'subtitle' => 'Selected projects across web, mobile, and product platforms.',
                                            'extra_crumbs' => [],
                                            'translations' => [
                                                'ar' => [
                                                    'home_label' => 'الرئيسية',
                                                    'eyebrow' => 'المحفظة',
                                                    'title_override' => 'أعمالنا',
                                                    'subtitle' => 'مشاريع مختارة عبر الويب والموبايل ومنصات المنتجات.',
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
                'id' => 'band_work_projects',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_work_projects',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_work_projects',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_work_projects_list',
                                        'kind' => 'kit',
                                        'type' => 'projects_list',
                                        'enabled' => true,
                                        'settings' => [
                                            'show_filters' => true,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'band_work_cta',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_work_cta',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_work_cta',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_work_cta',
                                        'kind' => 'kit',
                                        'type' => 'cta',
                                        'enabled' => true,
                                        'settings' => [
                                            'title' => 'Have a project in mind?',
                                            'subtitle' => 'Tell us about your goals. We respond within one business day.',
                                            'button_label' => 'Get in touch',
                                            'button_url' => '',
                                            'translations' => [
                                                'ar' => [
                                                    'title' => 'هل لديك مشروع في بالك؟',
                                                    'subtitle' => 'أخبرنا عن أهدافك. نرد خلال يوم عمل واحد.',
                                                    'button_label' => 'تواصل معنا',
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
