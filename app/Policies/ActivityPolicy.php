<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Spatie\Activitylog\Models\Activity;

/**
 * Activity is a third-party model (spatie/laravel-activitylog), so Laravel's
 * policy auto-discovery never finds this class — it's registered explicitly
 * in AppServiceProvider::boot(). Read-only: no create/update/delete/restore,
 * since activity log rows are never edited by hand.
 */
class ActivityPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Activity');
    }

    public function view(AuthUser $authUser, Activity $activity): bool
    {
        return $authUser->can('View:Activity');
    }
}
