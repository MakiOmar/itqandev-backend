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
            ['name' => 'Web Development', 'slug' => 'web-development', 'description' => 'Custom websites, SPAs, and PWAs'],
            ['name' => 'UI/UX Design', 'slug' => 'ui-ux-design', 'description' => 'Human-centered interfaces and design systems'],
            ['name' => 'E-commerce', 'slug' => 'ecommerce', 'description' => 'Online stores, payments, and product catalogs'],
            ['name' => 'Mobile Apps', 'slug' => 'mobile-apps', 'description' => 'Cross-platform apps with performant backends'],
            ['name' => 'DevOps & Cloud', 'slug' => 'devops-cloud', 'description' => 'CI/CD, containers, and cloud infrastructure'],
            ['name' => 'SEO & Performance', 'slug' => 'seo-performance', 'description' => 'Core Web Vitals, on-page SEO, and speed audits'],
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
                'name' => 'Sarah Mitchell',
                'role' => 'Marketing Director, BrightWave',
                'content' => 'The team delivered a blazing-fast marketing site and a flexible CMS that our content team loves. Core Web Vitals jumped instantly.',
            ],
            [
                'name' => 'David Chen',
                'role' => 'Founder, ShopNorth',
                'content' => 'Our new e-commerce build doubled conversion. Checkout is seamless, and the devs handled payments, inventory, and analytics end-to-end.',
            ],
            [
                'name' => 'Lina Alvarez',
                'role' => 'Product Manager, FinEdge',
                'content' => 'They shipped a secure client portal with great UX and CI/CD ready from day one. Fast iterations, clear communication, zero surprises.',
            ],
        ];

        foreach ($testimonials as $t) {
            Testimonial::updateOrCreate(
                ['name' => $t['name'], 'role' => $t['role']],
                array_merge($t, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }
}

