<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Generation;
use Illuminate\Auth\Access\HandlesAuthorization;

class GenerationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Generation');
    }

    public function view(AuthUser $authUser, Generation $generation): bool
    {
        return $authUser->can('View:Generation');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Generation');
    }

    public function update(AuthUser $authUser, Generation $generation): bool
    {
        return $authUser->can('Update:Generation');
    }

    public function delete(AuthUser $authUser, Generation $generation): bool
    {
        return $authUser->can('Delete:Generation');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Generation');
    }

    public function restore(AuthUser $authUser, Generation $generation): bool
    {
        return $authUser->can('Restore:Generation');
    }

    public function forceDelete(AuthUser $authUser, Generation $generation): bool
    {
        return $authUser->can('ForceDelete:Generation');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Generation');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Generation');
    }

    public function replicate(AuthUser $authUser, Generation $generation): bool
    {
        return $authUser->can('Replicate:Generation');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Generation');
    }

}