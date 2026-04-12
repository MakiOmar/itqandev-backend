<?php

namespace App\Providers;

use App\Models\AppMedia;
use App\Models\Category;
use App\Models\Project;
use App\Models\Service;
use App\Policies\CategoryPolicy;
use App\Policies\ServicePolicy;
use App\Policies\MediaPolicy;
use App\Policies\ProjectPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

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
        Service::class => ServicePolicy::class,
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

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Authenticated dashboard traffic: higher ceiling; guests (health, login) stay bounded.
        RateLimiter::for('api', function (Request $request) {
            if (app()->environment('local')) {
                return Limit::none();
            }

            $user = $request->user();
            $perMinute = $user
                ? max(1, min((int) env('API_RATE_LIMIT_AUTHENTICATED_PER_MINUTE', 300), 5000))
                : max(1, min((int) env('API_RATE_LIMIT_GUEST_PER_MINUTE', 120), 1000));

            $key = $user ? 'user:' . $user->getAuthIdentifier() : 'ip:' . $request->ip();

            return Limit::perMinute($perMinute)->by($key);
        });

        RateLimiter::for('login', function (Request $request) {
            if (app()->environment('local')) {
                return Limit::none();
            }

            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perHour(30)->by($request->ip()),
            ];
        });

        RateLimiter::for('uploads', function (Request $request) {
            if (app()->environment('local')) {
                return Limit::none();
            }
            $n = max(1, min((int) env('UPLOAD_RATE_LIMIT_PER_MINUTE', 30), 200));

            return Limit::perMinute($n)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('bulk', function (Request $request) {
            if (app()->environment('local')) {
                return Limit::none();
            }
            $n = max(1, min((int) env('BULK_RATE_LIMIT_PER_MINUTE', 10), 120));

            return Limit::perMinute($n)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('health', function (Request $request) {
            if (app()->environment('local')) {
                return Limit::none();
            }
            $n = max(1, min((int) env('HEALTH_CHECK_RATE_LIMIT_PER_MINUTE', 200), 2000));

            return Limit::perMinute($n)->by($request->ip());
        });
    }
}
