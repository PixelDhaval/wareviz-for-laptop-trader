---
paths:
  - 'app/Models/*.php,app/Policies/ActivityPolicy.php,app/Providers/AppServiceProvider.php,database/seeders/ShieldSeeder.php'
---

# Seeders

## Activity logging: spatie/laravel-activitylog v5 namespaces, DB-default refresh, and Shield policy for a third-party model
Installed `spatie/laravel-activitylog` (^5.1) + `bokshorn-it/filament-activity-timeline` (^1.4) for an audit trail on Laptop, Shipment, Sale, SaleItem, RepairJob, Buyer, Supplier, Setting. Each model implements `BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle` and uses `LogsActivity` with `getActivitylogOptions()` returning `LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()`.

**v5 moved these from the v4 namespaces the plugin's own docs page shows**: `LogsActivity` is `Spatie\Activitylog\Models\Concerns\LogsActivity` (not `Spatie\Activitylog\Traits\LogsActivity`), `LogOptions` is `Spatie\Activitylog\Support\LogOptions` (not `Spatie\Activitylog\LogOptions`), and `dontSubmitEmptyLogs()` is now `dontLogEmptyChanges()`. Getting this wrong doesn't throw a normal PHP error under `php artisan test` — `vendor/laravel/pao`'s stdout stream filter swallows the fatal-error output entirely, so the test run silently exits with zero output. Always check `storage/logs/laravel.log` when a test run produces no output at all.

**A model with a DB-level column default must `refresh()` itself right after `created`**, or its in-memory instance keeps those columns `null` — `logOnlyDirty()` then diffs the *next* update's fresh DB values against that stale `null` original and logs a phantom "changed from null to X" for every defaulted column. This is the same root cause already documented for `Setting::current()` in [[pages]]. Fixed via a `static::created()` listener calling `$model->refresh()` on `Shipment` (5 cost/rate columns default 0/1) and `RepairJob` (`cost_exchange_rate` defaults to 1).

**`Spatie\Activitylog\Models\Activity` needs its policy registered explicitly** — `Gate::policy(Activity::class, ActivityPolicy::class)` in `AppServiceProvider::boot()` — since it's outside `App\Models` and Laravel's naming-convention auto-discovery never finds it. Its permissions (`ViewAny:Activity`, `View:Activity`) also can't come from `shield:generate --all`, since Shield's discovery never sees a resource that ships inside a vendor package — they're created by hand in `ShieldSeeder`, same pattern as [[relation-managers]]'s SaleItem note about policy-less models.
