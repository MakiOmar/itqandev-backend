<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Skill;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class WebDevDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $categories = [
            ['name' => 'تطوير الويب', 'slug' => 'web-development', 'description' => 'مواقع مخصصة، تطبيقات أحادية الصفحة، وتجارب ويب تقدمية'],
            ['name' => 'تصميم واجهات وتجربة المستخدم', 'slug' => 'ui-ux-design', 'description' => 'تصميمات متمحورة حول المستخدم وأنظمة تصميم متسقة'],
            ['name' => 'التجارة الإلكترونية', 'slug' => 'ecommerce', 'description' => 'متاجر إلكترونية، بوابات دفع، وإدارة المنتجات'],
            ['name' => 'تطبيقات الجوال', 'slug' => 'mobile-apps', 'description' => 'تطبيقات متعددة المنصات مع واجهات برمجة سريعة'],
            ['name' => 'الحوسبة السحابية وعمليات التطوير', 'slug' => 'devops-cloud', 'description' => 'تكامل مستمر، حاويات، وبنية تحتية سحابية'],
            ['name' => 'تحسين الأداء ومحركات البحث', 'slug' => 'seo-performance', 'description' => 'تحسين مؤشرات الويب الحيوية، وتحسين السرعة وتحسين محركات البحث'],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(
                ['slug' => $cat['slug']],
                array_merge($cat, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        $skills = [
            ['name' => 'JavaScript', 'slug' => 'javascript'],
            ['name' => 'TypeScript', 'slug' => 'typescript'],
            ['name' => 'Vue.js', 'slug' => 'vuejs'],
            ['name' => 'React', 'slug' => 'react'],
            ['name' => 'Node.js', 'slug' => 'nodejs'],
            ['name' => 'Laravel', 'slug' => 'laravel'],
            ['name' => 'PHP', 'slug' => 'php'],
            ['name' => 'MySQL', 'slug' => 'mysql'],
            ['name' => 'PostgreSQL', 'slug' => 'postgresql'],
            ['name' => 'Docker', 'slug' => 'docker'],
            ['name' => 'AWS', 'slug' => 'aws'],
            ['name' => 'CI/CD', 'slug' => 'ci-cd'],
            ['name' => 'Tailwind CSS', 'slug' => 'tailwind-css'],
            ['name' => 'REST APIs', 'slug' => 'rest-apis'],
        ];

        foreach ($skills as $skill) {
            Skill::updateOrCreate(
                ['slug' => $skill['slug']],
                array_merge($skill, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        $testimonials = [
            [
                'client_name' => 'سارة ميتشيل',
                'client_role' => 'مديرة تسويق',
                'company' => 'برايت ويف',
                'rating' => 5,
                'content' => 'الفريق نفّذ موقعًا تسويقيًا سريعًا جدًا مع نظام إدارة محتوى مرن أحبه فريق المحتوى لدينا. مؤشرات Core Web Vitals تحسنت فورًا.',
            ],
            [
                'client_name' => 'ديفيد تشين',
                'client_role' => 'مؤسس',
                'company' => 'شوب نورث',
                'rating' => 5,
                'content' => 'متجرنا الإلكتروني الجديد ضاعف معدل التحويل. عملية الدفع سلسة والفريق تولى المدفوعات والمخزون والتحليلات بالكامل.',
            ],
            [
                'client_name' => 'لينا ألفاريز',
                'client_role' => 'مديرة منتج',
                'company' => 'فين إيدج',
                'rating' => 5,
                'content' => 'تم إطلاق بوابة عملاء آمنة مع تجربة مستخدم ممتازة وخط تكامل/نشر مستمر منذ اليوم الأول. سرعات تسليم عالية وتواصل واضح بلا مفاجآت.',
            ],
        ];

        foreach ($testimonials as $t) {
            Testimonial::updateOrCreate(
                [
                    'client_name' => $t['client_name'],
                    'client_role' => $t['client_role'],
                    'company' => $t['company'],
                ],
                array_merge($t, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }
}

