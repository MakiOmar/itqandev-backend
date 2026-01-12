<?php

namespace App\Providers;

use App\Models\AppMedia;
use App\Models\Category;
use App\Models\Project;
use App\Policies\CategoryPolicy;
use App\Policies\MediaPolicy;
use App\Policies\ProjectPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Project::class => ProjectPolicy::class,
        Category::class => CategoryPolicy::class,
        AppMedia::class => MediaPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // General API rate limiting
        RateLimiter::for('api', function (Request $request) {
            return [
                Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip()),
            ];
        });

        // Stricter rate limiting for login
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
            ];
        });

        // Rate limiting for file uploads
        RateLimiter::for('uploads', function (Request $request) {
            return [
                Limit::perMinute(10)->by(optional($request->user())->id ?: $request->ip()),
            ];
        });

        // Rate limiting for bulk operations
        RateLimiter::for('bulk', function (Request $request) {
            return [
                Limit::perMinute(3)->by(optional($request->user())->id ?: $request->ip()),
            ];
        });
    }
}
