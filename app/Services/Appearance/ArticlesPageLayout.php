<?php

namespace App\Services\Appearance;

/**
 * Default Articles (blog listing) CMS page layout (page builder document).
 * Used by {@see \Database\Seeders\ArticlesPageSeeder}.
 * Primary (English) copy on flat settings; Arabic under settings.translations.ar.
 * Public marketing URL stays `/{lang}/blog/`; CMS page slug is `articles`.
 */
final class ArticlesPageLayout
{
    /** Bump when seeded layout shape should replace older articles pages missing this marker. */
    public const LAYOUT_REVISION = 1;

    /**
     * @return list<array<string, mixed>>
     */
    public static function sections(): array
    {
        return PageLayoutDocument::normalizeSectionsForPages([
            [
                'id' => 'band_articles_header',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [
                    'layout_revision' => self::LAYOUT_REVISION,
                ],
                'rows' => [
                    [
                        'id' => 'row_articles_header',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_articles_header',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_articles_page_header',
                                        'kind' => 'kit',
                                        'type' => 'page_header',
                                        'enabled' => true,
                                        'settings' => [
                                            'show_breadcrumbs' => true,
                                            'show_title' => true,
                                            'home_label' => 'Home',
                                            'eyebrow' => 'Insights',
                                            'title_override' => 'Articles',
                                            'subtitle' => 'Updates, tips, and insights from our team.',
                                            'extra_crumbs' => [],
                                            'translations' => [
                                                'ar' => [
                                                    'home_label' => 'الرئيسية',
                                                    'eyebrow' => 'رؤى',
                                                    'title_override' => 'المقالات',
                                                    'subtitle' => 'تحديثات ونصائح ورؤى من فريقنا.',
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
                'id' => 'band_articles_posts',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_articles_posts',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_articles_posts',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_articles_blog_posts_list',
                                        'kind' => 'kit',
                                        'type' => 'blog_posts_list',
                                        'enabled' => true,
                                        'settings' => [
                                            'per_page' => 12,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'band_articles_cta',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_articles_cta',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_articles_cta',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_articles_cta',
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
