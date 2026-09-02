<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RepairJob;
use Illuminate\Auth\Access\HandlesAuthorization;

class RepairJobPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RepairJob');
    }

    public function view(AuthUser $authUser, RepairJob $repairJob): bool
    {
        return $authUser->can('View:RepairJob');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RepairJob');
    }

    public function update(AuthUser $authUser, RepairJob $repairJob): bool
    {
        return $authUser->can('Update:RepairJob');
    }

    public function delete(AuthUser $authUser, RepairJob $repairJob): bool
    {
        return $authUser->can('Delete:RepairJob');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RepairJob');
    }

    public function restore(AuthUser $authUser, RepairJob $repairJob): bool
    {
        return $authUser->can('Restore:RepairJob');
    }

    public function forceDelete(AuthUser $authUser, RepairJob $repairJob): bool
    {
        return $authUser->can('ForceDelete:RepairJob');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RepairJob');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RepairJob');
    }

    public function replicate(AuthUser $authUser, RepairJob $repairJob): bool
    {
        return $authUser->can('Replicate:RepairJob');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RepairJob');
    }

}