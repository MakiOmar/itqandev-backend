<?php

namespace App\Services\Appearance;

/**
 * Default professional About CMS page layout (page builder document).
 * Used by {@see \Database\Seeders\AboutPageSeeder} and as the canonical starter shape.
 * Primary (English) copy lives on flat settings; Arabic under settings.translations.ar.
 */
final class AboutPageLayout
{
    /** Bump when seeded layout shape should replace older about pages missing this marker. */
    public const LAYOUT_REVISION = 2;

    /**
     * @return list<array<string, mixed>>
     */
    public static function sections(): array
    {
        return PageLayoutDocument::normalizeSectionsForPages([
            [
                'id' => 'band_about_header',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [
                    'layout_revision' => self::LAYOUT_REVISION,
                ],
                'rows' => [
                    [
                        'id' => 'row_about_header',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_about_header',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_about_page_header',
                                        'kind' => 'kit',
                                        'type' => 'page_header',
                                        'enabled' => true,
                                        'settings' => [
                                            'show_breadcrumbs' => true,
                                            'show_title' => true,
                                            'home_label' => 'Home',
                                            'eyebrow' => 'About',
                                            'title_override' => 'Building digital products since 2014',
                                            'subtitle' => 'A product-minded engineering studio. Careful craft, honest timelines, and partnerships measured in years—not sprints.',
                                            'extra_crumbs' => [],
                                            'translations' => [
                                                'ar' => [
                                                    'home_label' => 'الرئيسية',
                                                    'eyebrow' => 'من نحن',
                                                    'title_override' => 'نبني منتجات رقمية منذ ٢٠١٤',
                                                    'subtitle' => 'استوديو هندسي بمنظور المنتج. حرفية دقيقة، جداول زمنية صادقة، وشراكات تُقاس بالسنوات لا بالسبرينتات.',
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
                'id' => 'band_about_intro',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_about_intro',
                        'stack_below' => 'none',
                        'gap' => 6,
                        'columns' => [
                            [
                                'id' => 'col_about_intro',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_about_intro',
                                        'kind' => 'kit',
                                        'type' => 'image_text',
                                        'enabled' => true,
                                        'settings' => [
                                            'eyebrow' => 'Who we are',
                                            'title' => 'We present ourselves plainly',
                                            'body' => '<p>Since <strong>2014</strong> we have partnered with ambitious teams to ship web and mobile products that stay fast, reliable, and a pleasure to use.</p><p>We are not a volume agency. We are a focused studio: senior engineering judgment, design that earns trust, and communication you can brief a stakeholder with.</p>',
                                            'image' => null,
                                            'image_position' => 'right',
                                            'button_label' => 'View our work',
                                            'button_url' => '/work',
                                            'translations' => [
                                                'ar' => [
                                                    'eyebrow' => 'من نكون',
                                                    'title' => 'نقدّم أنفسنا بوضوح',
                                                    'body' => '<p>منذ <strong>٢٠١٤</strong> نشارك فرقًا طموحة في إطلاق منتجات ويب وموبايل تبقى سريعة وموثوقة وممتعة في الاستخدام.</p><p>لسنا وكالة حجم. نحن استوديو مركّز: حكم هندسي ناضج، تصميم يبني الثقة، وتواصل يمكنك عرضه على أصحاب المصلحة بثقة.</p>',
                                                    'button_label' => 'اطّلع على أعمالنا',
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
                'id' => 'band_about_stats',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_about_stats',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_about_stats',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_about_stats',
                                        'kind' => 'kit',
                                        'type' => 'stats',
                                        'enabled' => true,
                                        'settings' => [
                                            'title' => 'By the numbers',
                                            'items' => [
                                                ['value' => 12, 'label' => 'Years in the field'],
                                                ['value' => 50, 'label' => 'Products & platforms shipped'],
                                                ['value' => 99, 'label' => 'Client satisfaction'],
                                            ],
                                            'translations' => [
                                                'ar' => [
                                                    'title' => 'بالأرقام',
                                                    'items' => [
                                                        ['value' => 12, 'label' => 'عامًا في المجال'],
                                                        ['value' => 50, 'label' => 'منتجًا ومنصةً أُطلقت'],
                                                        ['value' => 99, 'label' => 'رضا العملاء'],
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
                'id' => 'band_about_timeline',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_about_timeline',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_about_timeline',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_about_timeline',
                                        'kind' => 'kit',
                                        'type' => 'timeline',
                                        'enabled' => true,
                                        'settings' => [
                                            'title' => 'Our journey',
                                            'subtitle' => 'A vertical look at how the studio grew—from first client builds to today’s product partnerships.',
                                            'items' => [
                                                [
                                                    'year' => '2014',
                                                    'title' => 'Foundations',
                                                    'description' => 'Entered the field building serious web applications for teams who needed more than a brochure site—clear scope, solid backends, and interfaces that hold up.',
                                                ],
                                                [
                                                    'year' => '2016',
                                                    'title' => 'Product systems',
                                                    'description' => 'Moved from one-off launches to longer product engagements: roadmaps, iterative delivery, and design systems that scale with the business.',
                                                ],
                                                [
                                                    'year' => '2018',
                                                    'title' => 'Mobile & modern stacks',
                                                    'description' => 'Expanded into native and cross-platform mobile alongside modern web stacks—Laravel, Node, React, Vue, and careful API design.',
                                                ],
                                                [
                                                    'year' => '2020',
                                                    'title' => 'Remote delivery, same craft',
                                                    'description' => 'Refined remote collaboration without diluting quality: structured demos, written decisions, and releases you can trust under pressure.',
                                                ],
                                                [
                                                    'year' => '2022',
                                                    'title' => 'Platform thinking',
                                                    'description' => 'Helped clients think in platforms—reusable modules, performance budgets, accessibility, and admin experiences operators actually enjoy.',
                                                ],
                                                [
                                                    'year' => '2026',
                                                    'title' => 'Still shipping',
                                                    'description' => 'Twelve years in, the brief is unchanged: digital products that scale, partnerships that last, and work we are proud to put our name on.',
                                                ],
                                            ],
                                            'translations' => [
                                                'ar' => [
                                                    'title' => 'رحلتنا',
                                                    'subtitle' => 'نظرة عمودية على نمو الاستوديو—من أولى المشاريع إلى شراكات المنتجات اليوم.',
                                                    'items' => [
                                                        [
                                                            'year' => '٢٠١٤',
                                                            'title' => 'الأسس',
                                                            'description' => 'دخلنا المجال ببناء تطبيقات ويب جادة لفرق تحتاج أكثر من موقع تعريفي—نطاق واضح، خلفيات متينة، وواجهات تصمد.',
                                                        ],
                                                        [
                                                            'year' => '٢٠١٦',
                                                            'title' => 'أنظمة المنتج',
                                                            'description' => 'انتقلنا من إطلاقات منفصلة إلى انخراطات منتج أطول: خرائط طريق، تسليم تكراري، وأنظمة تصميم تنمو مع العمل.',
                                                        ],
                                                        [
                                                            'year' => '٢٠١٨',
                                                            'title' => 'الموبايل والمكدّسات الحديثة',
                                                            'description' => 'توسّعنا في الموبايل الأصلي ومتعدد المنصات إلى جانب مكدّسات الويب الحديثة—Laravel وNode وReact وVue وتصميم واجهات برمجة حريص.',
                                                        ],
                                                        [
                                                            'year' => '٢٠٢٠',
                                                            'title' => 'تسليم عن بُعد بنفس الحرفية',
                                                            'description' => 'صقلنا التعاون عن بُعد دون تخفيف الجودة: عروض منظمة، قرارات مكتوبة، وإصدارات يمكن الوثوق بها تحت الضغط.',
                                                        ],
                                                        [
                                                            'year' => '٢٠٢٢',
                                                            'title' => 'تفكير المنصّة',
                                                            'description' => 'ساعدنا العملاء على التفكير كمنصّات—وحدات قابلة لإعادة الاستخدام، ميزانيات أداء، إمكانية وصول، وتجارب إدارة يستمتع بها المشغّلون.',
                                                        ],
                                                        [
                                                            'year' => '٢٠٢٦',
                                                            'title' => 'ما زلنا نُطلق',
                                                            'description' => 'بعد اثني عشر عامًا، الموجز لم يتغيّر: منتجات رقمية تتوسع، شراكات تدوم، وعمل نفتخر بوضع اسمنا عليه.',
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
                'id' => 'band_about_values',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_about_values',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_about_values',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_about_values',
                                        'kind' => 'kit',
                                        'type' => 'feature_grid',
                                        'enabled' => true,
                                        'settings' => [
                                            'title' => 'What we stand for',
                                            'items' => [
                                                [
                                                    'title' => 'Quality without theatre',
                                                    'description' => 'We ship work that holds up in production—performance, accessibility, and maintainability included, not bolted on later.',
                                                ],
                                                [
                                                    'title' => 'Clarity over jargon',
                                                    'description' => 'You always know where the project stands: risks named early, milestones you can calendar, and demos that show real progress.',
                                                ],
                                                [
                                                    'title' => 'Partnership over transactions',
                                                    'description' => 'We prefer relationships measured in years. After launch we stay available for the next iteration, not the next invoice alone.',
                                                ],
                                                [
                                                    'title' => 'Inclusive by default',
                                                    'description' => 'Interfaces should welcome every user. We design and build with accessibility as a first-class requirement.',
                                                ],
                                            ],
                                            'translations' => [
                                                'ar' => [
                                                    'title' => 'ما نؤمن به',
                                                    'items' => [
                                                        [
                                                            'title' => 'جودة بلا استعراض',
                                                            'description' => 'نُطلق عملًا يصمد في الإنتاج—الأداء وإمكانية الوصول وقابلية الصيانة ضمن الأساس لا كإضافة لاحقة.',
                                                        ],
                                                        [
                                                            'title' => 'وضوح بدل المصطلحات',
                                                            'description' => 'تعرف دائمًا أين يقف المشروع: مخاطر تُسمّى مبكرًا، معالم يمكن جدولتها، وعروض تُظهر تقدّمًا حقيقيًا.',
                                                        ],
                                                        [
                                                            'title' => 'شراكة لا صفقة',
                                                            'description' => 'نفضّل علاقات تُقاس بالسنوات. بعد الإطلاق نبقى متاحين للتكرار التالي، لا للفاتورة التالية وحدها.',
                                                        ],
                                                        [
                                                            'title' => 'شمول من البداية',
                                                            'description' => 'الواجهات يجب أن ترحّب بكل مستخدم. نصمّم ونبني مع إمكانية الوصول كمتطلب من الدرجة الأولى.',
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
                'id' => 'band_about_process',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_about_process',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_about_process',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_about_process',
                                        'kind' => 'kit',
                                        'type' => 'timeline',
                                        'enabled' => true,
                                        'settings' => [
                                            'title' => 'How we work',
                                            'subtitle' => 'A calm, repeatable process—discovery through launch—so every engagement feels deliberate.',
                                            'items' => [
                                                [
                                                    'year' => '01',
                                                    'title' => 'Discover',
                                                    'description' => 'Goals, constraints, success metrics, and the decisions that must be true before we design a pixel.',
                                                ],
                                                [
                                                    'year' => '02',
                                                    'title' => 'Design',
                                                    'description' => 'UX and UI aligned to your brand—prototypes you can react to, not decks you endure.',
                                                ],
                                                [
                                                    'year' => '03',
                                                    'title' => 'Build',
                                                    'description' => 'Iterative delivery with clear milestones, regular demos, and engineering you can hand to your team later.',
                                                ],
                                                [
                                                    'year' => '04',
                                                    'title' => 'Launch & evolve',
                                                    'description' => 'Ship with confidence, measure what matters, and keep improving after day one.',
                                                ],
                                            ],
                                            'translations' => [
                                                'ar' => [
                                                    'title' => 'كيف نعمل',
                                                    'subtitle' => 'عملية هادئة وقابلة للتكرار—من الاستكشاف إلى الإطلاق—ليبدو كل انخراط مدروسًا.',
                                                    'items' => [
                                                        [
                                                            'year' => '٠١',
                                                            'title' => 'اكتشف',
                                                            'description' => 'الأهداف والقيود ومقاييس النجاح والقرارات التي يجب أن تكون صحيحة قبل تصميم أي بكسل.',
                                                        ],
                                                        [
                                                            'year' => '٠٢',
                                                            'title' => 'صمّم',
                                                            'description' => 'تجربة وواجهة متوائمة مع علامتك—نماذج أولية تتفاعل معها لا عروضًا تتحملها.',
                                                        ],
                                                        [
                                                            'year' => '٠٣',
                                                            'title' => 'ابنِ',
                                                            'description' => 'تسليم تكراري بمعالم واضحة وعروض منتظمة وهندسة يمكنك تسليمها لفريقك لاحقًا.',
                                                        ],
                                                        [
                                                            'year' => '٠٤',
                                                            'title' => 'أطلق وطوّر',
                                                            'description' => 'أطلق بثقة، قِس ما يهم، واستمر في التحسين بعد اليوم الأول.',
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
                'id' => 'band_about_cta',
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_about_cta',
                        'stack_below' => 'none',
                        'gap' => 4,
                        'columns' => [
                            [
                                'id' => 'col_about_cta',
                                'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                'blocks' => [
                                    [
                                        'id' => 'kit_about_cta',
                                        'kind' => 'kit',
                                        'type' => 'cta',
                                        'enabled' => true,
                                        'settings' => [
                                            'title' => 'Ready to talk about your next release?',
                                            'subtitle' => 'Tell us where you are and where you need to be. We respond within one business day.',
                                            'button_label' => 'Get in touch',
                                            'button_url' => '',
                                            'translations' => [
                                                'ar' => [
                                                    'title' => 'هل أنت مستعد للحديث عن إصدارك القادم؟',
                                                    'subtitle' => 'أخبرنا أين تقف وأين تحتاج أن تكون. نرد خلال يوم عمل واحد.',
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
