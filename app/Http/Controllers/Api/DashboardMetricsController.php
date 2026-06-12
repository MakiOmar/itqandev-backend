<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppMedia as Media;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Project;
use App\Models\Service;
use App\Models\Skill;
use App\Models\Testimonial;
use App\Support\FeatureModules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardMetricsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $metrics = [
            'projects' => ['total' => 0, 'published' => 0, 'draft' => 0],
            'categories' => ['total' => 0],
            'skills' => ['total' => 0],
            'testimonials' => ['total' => 0],
            'blog' => ['total' => 0, 'published' => 0],
            'services' => ['total' => 0],
            'media' => ['total' => 0],
        ];

        if (FeatureModules::enabled('projects')) {
            $metrics['projects'] = [
                'total' => Project::query()->count(),
                'published' => Project::query()->where('status', 'published')->count(),
                'draft' => Project::query()->where('status', 'draft')->count(),
            ];
        }

        if (FeatureModules::enabled('categories')) {
            $metrics['categories']['total'] = Category::query()->count();
        }

        if (FeatureModules::enabled('skills')) {
            $metrics['skills']['total'] = Skill::query()->count();
        }

        if (FeatureModules::enabled('testimonials')) {
            $metrics['testimonials']['total'] = Testimonial::query()->count();
        }

        if (FeatureModules::enabled('blog')) {
            $metrics['blog'] = [
                'total' => BlogPost::query()->count(),
                'published' => BlogPost::query()->where('status', 'published')->count(),
            ];
        }

        if (FeatureModules::enabled('services')) {
            $metrics['services']['total'] = Service::query()->count();
        }

        if (FeatureModules::enabled('media')) {
            $metrics['media']['total'] = Media::query()->count();
        }

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }
}
