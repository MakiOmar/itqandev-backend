<?php

namespace App\Services\Appearance;

/**
 * Default professional Portfolio CMS page layout (page builder document).
 * Used by {@see \Database\Seeders\PortfolioPageSeeder}.
 * Primary (English) copy on flat settings; Arabic under settings.translations.ar.
 */
final class PortfolioPageLayout
{
    /** Bump when seeded layout shape should replace older portfolio pages missing this marker. */
    public const LAYOUT_REVISION = 2;

    /**
     * @return list<array<string, mixed>>
     */
    public static function sections(): array
    {
        return PageLayoutDocument::normalizeSectionsForPages([
            [
                'id' => 'band_portfolio_header',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [
                    'layout_revision' => self::LAYOUT_REVISION,
                ],
                'rows' => [
                    [
                        'id' => 'row_portfolio_header',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_portfolio_header',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_portfolio_page_header',
                                        'kind' => 'kit',
                                        'type' => 'page_header',
                                        'enabled' => true,
                                        'settings' => [
                                            'show_breadcrumbs' => true,
                                            'show_title' => true,
                                            'home_label' => 'Home',
                                            'eyebrow' => 'Selected projects',
                                            'title_override' => 'Portfolio',
                                            'subtitle' => 'Selected projects across web, mobile, and product platforms.',
                                            'extra_crumbs' => [],
                                            'translations' => [
                                                'ar' => [
                                                    'home_label' => 'الرئيسية',
                                                    'eyebrow' => 'مشاريع مختارة',
                                                    'title_override' => 'المحفظة',
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
                'id' => 'band_portfolio_projects',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_portfolio_projects',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_portfolio_projects',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_portfolio_projects_list',
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
                'id' => 'band_portfolio_cta',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_portfolio_cta',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_portfolio_cta',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_portfolio_cta',
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
