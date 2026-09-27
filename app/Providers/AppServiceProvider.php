<?php

namespace App\Providers;

use App\Policies\ActivityPolicy;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
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
        FilamentShield::enforcePolicies();

        // Spatie\Activitylog\Models\Activity is a third-party model, outside
        // App\Models, so Laravel's policy naming-convention auto-discovery
        // never finds ActivityPolicy — it must be registered explicitly.
        Gate::policy(Activity::class, ActivityPolicy::class);
    }
}
