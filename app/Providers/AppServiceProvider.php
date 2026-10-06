<?php

namespace App\Providers;

use App\Models\AppMedia;
use App\Models\BlogPost;
use App\Models\BuilderTemplate;
use App\Models\Category;
use App\Models\Font;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Project;
use App\Models\SeoMeta;
use App\Models\Service;
use App\Models\Skill;
use App\Models\Testimonial;
use App\Models\User;
use App\Policies\BlogPostPolicy;
use App\Policies\BuilderTemplatePolicy;
use App\Policies\CategoryPolicy;
use App\Policies\FontPolicy;
use App\Policies\MediaPolicy;
use App\Policies\MenuItemPolicy;
use App\Policies\MenuPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SeoMetaPolicy;
use App\Policies\ServicePolicy;
use App\Policies\SkillPolicy;
use App\Policies\TestimonialPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        User::class => UserPolicy::class,
        Project::class => ProjectPolicy::class,
        Category::class => CategoryPolicy::class,
        Font::class => FontPolicy::class,
        Service::class => ServicePolicy::class,
        AppMedia::class => MediaPolicy::class,
        BlogPost::class => BlogPostPolicy::class,
        Testimonial::class => TestimonialPolicy::class,
        Skill::class => SkillPolicy::class,
        SeoMeta::class => SeoMetaPolicy::class,
        Menu::class => MenuPolicy::class,
        MenuItem::class => MenuItemPolicy::class,
        BuilderTemplate::class => BuilderTemplatePolicy::class,
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

        // System cache endpoints (no dedicated model) — align with "manage system" permission.
        Gate::define('manageSystemCache', function (\App\Models\User $user): bool {
            if ($user->hasAnyRole(['super_admin', 'admin'])) {
                return true;
            }
            try {
                return $user->hasPermissionTo('manage system');
            } catch (\Exception) {
                return false;
            }
        });

        Gate::define('viewActivityLogs', function (\App\Models\User $user): bool {
            if ($user->hasAnyRole(['super_admin', 'admin'])) {
                return true;
            }
            try {
                return $user->hasAnyPermission(['view activity', 'manage system']);
            } catch (\Exception) {
                return false;
            }
        });

        Gate::define('manageSettings', function (\App\Models\User $user): bool {
            return $user->hasAnyRole(['super_admin', 'admin']);
        });

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
                ? (int) config('http-rate-limits.authenticated_per_minute', 300)
                : (int) config('http-rate-limits.guest_per_minute', 120);

            $key = $user ? 'user:'.$user->getAuthIdentifier() : 'ip:'.$request->ip();

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
            $n = (int) config('http-rate-limits.upload_per_minute', 30);

            return Limit::perMinute($n)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('bulk', function (Request $request) {
            if (app()->environment('local')) {
                return Limit::none();
            }
            $n = (int) config('http-rate-limits.bulk_per_minute', 10);

            return Limit::perMinute($n)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('health', function (Request $request) {
            if (app()->environment('local')) {
                return Limit::none();
            }
            $n = (int) config('http-rate-limits.health_per_minute', 200);

            return Limit::perMinute($n)->by($request->ip());
        });

        RateLimiter::for('form-submit', function (Request $request) {
            if (app()->environment('local')) {
                return Limit::none();
            }
            $n = (int) config('http-rate-limits.form_submit_per_minute', 8);
            $slug = (string) $request->route('slug');

            return Limit::perMinute($n)->by($request->ip().'|form:'.$slug);
        });
    }
}
