<?php

namespace App\Addons;

use App\Support\FeatureModules;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Base provider for addon packages. List the subclass in the package's `extra.laravel.providers`.
 *
 * Definitions register in register() so content types and widgets exist before the registries resolve.
 * Routes and migrations load only when the module is enabled, so a disabled addon adds no API surface.
 */
abstract class AddonServiceProvider extends ServiceProvider implements Addon
{
    public function register(): void
    {
        AddonRegistry::register($this);
    }

    public function boot(): void
    {
        if (! FeatureModules::enabled($this->moduleKey())) {
            return;
        }

        $routes = $this->apiRoutesPath();
        if ($routes !== null && ! $this->app->routesAreCached()) {
            Route::prefix('api')
                ->middleware(['api', 'feature.module:'.$this->moduleKey()])
                ->group($routes);
        }

        $migrations = $this->migrationsPath();
        if ($migrations !== null) {
            $this->loadMigrationsFrom($migrations);
        }

        $this->bootEnabled();
    }

    /** Hook for policies, observers, and listeners; runs only while the module is enabled. */
    protected function bootEnabled(): void {}

    /** Routes file mounted under `/api`, behind the `api` group and this module's gate. */
    protected function apiRoutesPath(): ?string
    {
        return null;
    }

    protected function migrationsPath(): ?string
    {
        return null;
    }

    public function contentTypes(): array
    {
        return [];
    }

    public function widgets(): array
    {
        return [];
    }

    public function kits(): array
    {
        return [];
    }

    public function adminNav(): array
    {
        return [];
    }
}
