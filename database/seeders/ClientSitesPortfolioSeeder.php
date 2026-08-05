<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Project;
use App\Models\Skill;
use App\Support\FeatureModules;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds real client portfolio projects from live websites.
 */
class ClientSitesPortfolioSeeder extends Seeder
{
    public function run(): void
    {
        if (! FeatureModules::enabled('projects')) {
            $this->command?->warn('Projects module disabled; skipping ClientSitesPortfolioSeeder.');

            return;
        }

        $now = Carbon::now();
        $screenshotDir = storage_path('app/portfolio-screenshots');

        $projects = [
            [
                'title' => 'Fitness Way — الرشاقة المثالية',
                'slug' => 'fitnessway-sa',
                'summary' => 'متجر إلكتروني سعودي لمنتجات الرشاقة والمكملات مع عروض واشتراكات وفروع متعددة.',
                'description' => '<p>منصة تجارة إلكترونية لمركز الرشاقة المثالية في السعودية، تتيح بيع منتجات التنحيف والمكملات والعناية بالبشرة مع عروض حصرية وباقات اشتراك وفحص واستشارة مجانية.</p><ul><li>كتالوج منتجات وعروض مجموعات</li><li>تقييمات العملاء وتجربة شراء عربية</li><li>دعم فروع متعددة والتوصيل داخل المملكة</li></ul>',
                'link_url' => 'https://fitnessway-sa.com',
                'featured' => true,
                'categories' => ['ecommerce', 'web-development'],
                'skills' => ['wordpress', 'woocommerce', 'php', 'mysql'],
                'screenshot' => 'fitnessway-sa.png',
                'translations' => [
                    [
                        'locale' => 'en',
                        'title' => 'Fitness Way — Ideal Fitness',
                        'summary' => 'Saudi e-commerce store for weight-loss and wellness products with offers, subscriptions, and multi-branch support.',
                        'description' => '<p>An Arabic e-commerce experience for Fitness Way (Ideal Fitness) in Saudi Arabia: supplements, exclusive bundles, subscription packages, and free consultation booking.</p>',
                    ],
                ],
            ],
            [
                'title' => 'معهد البيان للخدمات التعليمية',
                'slug' => 'albyan-institute',
                'summary' => 'موقع أكاديمية تدريبية في دبي لبرامج ودبلومات مهنية حضورية وأونلاين.',
                'description' => '<p>منصة تعليمية لمعهد البيان في الإمارات تعرض الدورات والدبلومات المهنية (إعلام، إدارة، تقنية، لغات) مع تسجيل وسلة تسوق وشهادات معتمدة.</p><ul><li>عرض البرامج والأسعار بالدرهم</li><li>تسجيل ومتابعة للمتدربين</li><li>محتوى عربي موجّه لسوق العمل في الإمارات</li></ul>',
                'link_url' => 'https://albyan.institute',
                'featured' => true,
                'categories' => ['web-development', 'research-education'],
                'skills' => ['php', 'mysql', 'javascript', 'rest-apis'],
                'screenshot' => 'albyan-institute.png',
                'translations' => [
                    [
                        'locale' => 'en',
                        'title' => 'AlByan Institute',
                        'summary' => 'Dubai training institute site for professional diplomas and courses — onsite and online.',
                        'description' => '<p>Educational platform for AlByan Institute (UAE) showcasing professional programs in media, management, technology, and languages with enrollment and certified training pathways.</p>',
                    ],
                ],
            ],
            [
                'title' => 'جلسة — تطبيق إدارة جلسات المعالجين',
                'slug' => 'jalsah-app',
                'summary' => 'منصة SaaS لحجز الجلسات والمدفوعات والمكالمات دون اشتراك شهري على المعالج.',
                'description' => '<p>تطبيق جلسة يمنح مقدّم الخدمة موقعاً خاصاً خلال 48 ساعة لتنظيم المواعيد، استقبال المدفوعات، المكالمات الصوت/فيديو، التنبيهات، وأكواد الخصم — بدون اشتراك شهري أو نسبة من سعر الجلسة.</p><ul><li>جدولة جلسات أونلاين وأوفلاين</li><li>مدفوعات فيزا/ماستر ووسائل دفع مصرية</li><li>تسعير حسب الدولة واتصالات مدمجة</li></ul>',
                'link_url' => 'https://jalsah.app/',
                'featured' => true,
                'categories' => ['web-development', 'mobile-applications'],
                'skills' => ['javascript', 'rest-apis', 'mysql', 'nodejs'],
                'screenshot' => 'jalsah-app.png',
                'translations' => [
                    [
                        'locale' => 'en',
                        'title' => 'Jalsah App — Therapist Session Platform',
                        'summary' => 'SaaS for therapists: booking, payments, and built-in video calls without a monthly subscription fee.',
                        'description' => '<p>Jalsah gives providers a branded booking site within 48 hours — scheduling, payments, notifications, discount codes, and integrated voice/video sessions.</p>',
                    ],
                ],
            ],
            [
                'title' => 'جلسة أونلاين — دليل المعالجين النفسيين',
                'slug' => 'jalsah-online',
                'summary' => 'دليل معالجين نفسيين وأسرة في مصر مع حجز أونلاين ومساعد ذكي لاختيار المعالج.',
                'description' => '<p>موقع جلسة أونلاين يربط العملاء بأفضل المعالجين المعتمدين في مصر مع محادثة ذكاء اصطناعي لاختيار المعالج المناسب، حجز جلسات فيديو، ودفع آمن، وجلسات عبر نظام Jitsi المشفّر.</p><ul><li>دليل معالجين معتمد</li><li>مساعد AI لاختيار المعالج</li><li>حجز ودفع أونلاين وخدمة روشتة</li></ul>',
                'link_url' => 'https://jalsah.online/',
                'featured' => true,
                'categories' => ['web-development', 'user-interface-and-experience-design'],
                'skills' => ['javascript', 'rest-apis', 'mysql', 'tailwind-css'],
                'screenshot' => 'jalsah-online.png',
                'translations' => [
                    [
                        'locale' => 'en',
                        'title' => 'Jalsah Online — Therapist Directory',
                        'summary' => 'Directory of certified mental-health therapists in Egypt with AI matching and online booking.',
                        'description' => '<p>Jalsah Online connects clients with accredited therapists, AI-assisted matching, encrypted video sessions (Jitsi), and secure payments.</p>',
                    ],
                ],
            ],
            [
                'title' => 'فيتازون — متجر فيتامينات ومكملات',
                'slug' => 'vitazonei',
                'summary' => 'متجر إلكتروني مصري للفيتامينات والمكملات مع مدونة محتوى صحي وتوصيل لجميع المحافظات.',
                'description' => '<p>منصة VitaZone للتجارة الإلكترونية في مصر متخصصة في الفيتامينات والمكملات الغذائية، مع مقارنة منتجات، حسابات مستخدمين، وتتبع طلبات، بالإضافة إلى مدونة مقالات صحية داعمة للشراء.</p><ul><li>كتالوج فيتامينات ومكملات</li><li>مدونة محتوى (مقالات صحية)</li><li>شحن وتوصيل داخل مصر</li></ul>',
                'link_url' => 'https://vitazonei.com/',
                'demo_url' => 'https://vitazonei.com/articles/',
                'featured' => false,
                'categories' => ['ecommerce', 'web-development'],
                'skills' => ['wordpress', 'woocommerce', 'php', 'mysql'],
                'screenshot' => 'vitazonei.png',
                'translations' => [
                    [
                        'locale' => 'en',
                        'title' => 'VitaZone — Vitamins & Supplements Store',
                        'summary' => 'Egyptian e-commerce for vitamins and supplements with a health content blog and nationwide delivery.',
                        'description' => '<p>VitaZone is an Egypt-focused vitamins and supplements marketplace with product comparison, order tracking, and an educational articles section.</p>',
                    ],
                ],
            ],
            [
                'title' => 'شركة كروم للتشغيل والصيانة',
                'slug' => 'chorome-sa',
                'summary' => 'موقع تعريفي لشركة مقاولات وصيانة سعودية متخصصة في المرافق والمطاعم والمعارض.',
                'description' => '<p>موقع شركة كروم للتشغيل والصيانة والمقاولات في الرياض يعرض نبذة الشركة وخدماتها (تكييف، كهرباء، صيانة مرافق، تخطيط وتصميم) ومحفظة مشاريع في قطاع المطاعم والمعارض منذ 2014.</p><ul><li>عرض خدمات إدارة المرافق</li><li>قطاعات ومشاريع منجزة</li><li>هوية مؤسسية عربية احترافية</li></ul>',
                'link_url' => 'https://chorome-sa.com/',
                'featured' => false,
                'categories' => ['web-development', 'user-interface-and-experience-design'],
                'skills' => ['javascript', 'php', 'wordpress', 'tailwind-css'],
                'screenshot' => 'chorome-sa.png',
                'translations' => [
                    [
                        'locale' => 'en',
                        'title' => 'Chorome — Operations & Maintenance',
                        'summary' => 'Corporate site for a Saudi contracting and facility-maintenance company serving restaurants and retail.',
                        'description' => '<p>Chorome presents facility management services (HVAC, electrical, plumbing, landscaping) and completed projects across restaurants and commercial spaces in Saudi Arabia.</p>',
                    ],
                ],
            ],
        ];

        foreach ($projects as $proj) {
            $project = Project::updateOrCreate(
                ['slug' => $proj['slug']],
                [
                    'title' => $proj['title'],
                    'summary' => $proj['summary'],
                    'description' => $proj['description'],
                    'status' => 'published',
                    'featured' => $proj['featured'],
                    'published_at' => $now,
                    'link_url' => $proj['link_url'],
                    'demo_url' => $proj['demo_url'] ?? null,
                    'content_locale' => 'ar',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            if (! empty($proj['categories']) && FeatureModules::enabled('categories')) {
                $catIds = Category::whereIn('slug', $proj['categories'])->pluck('id');
                $project->categories()->sync($catIds);
            }

            if (! empty($proj['skills']) && FeatureModules::enabled('skills')) {
                $skillIds = Skill::whereIn('slug', $proj['skills'])->pluck('id');
                $project->skills()->sync($skillIds);
            }

            if (! empty($proj['translations'])) {
                foreach ($proj['translations'] as $row) {
                    $project->translations()->updateOrCreate(
                        ['locale' => $row['locale']],
                        [
                            'title' => $row['title'],
                            'summary' => $row['summary'],
                            'description' => $row['description'],
                        ]
                    );
                }
            }

            $shot = $screenshotDir.DIRECTORY_SEPARATOR.$proj['screenshot'];
            if (is_file($shot)) {
                $project->clearMediaCollection('hero');
                $project
                    ->addMedia($shot)
                    ->preservingOriginal()
                    ->usingFileName($proj['screenshot'])
                    ->toMediaCollection('hero');
            } else {
                $fallback = storage_path('app/public/default.png');
                if (is_file($fallback) && ! $project->getFirstMedia('hero')) {
                    $project
                        ->addMedia($fallback)
                        ->preservingOriginal()
                        ->toMediaCollection('hero');
                }
                $this->command?->warn("Screenshot missing for {$proj['slug']}: {$shot}");
            }

            $this->command?->info("Upserted project: {$proj['slug']} (#{$project->id})");
        }
    }
}
