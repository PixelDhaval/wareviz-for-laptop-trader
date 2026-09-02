<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LaptopModel;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LaptopModelPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LaptopModel');
    }

    public function view(AuthUser $authUser, LaptopModel $laptopModel): bool
    {
        return $authUser->can('View:LaptopModel');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LaptopModel');
    }

    public function update(AuthUser $authUser, LaptopModel $laptopModel): bool
    {
        return $authUser->can('Update:LaptopModel');
    }

    public function delete(AuthUser $authUser, LaptopModel $laptopModel): bool
    {
        return $authUser->can('Delete:LaptopModel');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LaptopModel');
    }

    public function restore(AuthUser $authUser, LaptopModel $laptopModel): bool
    {
        return $authUser->can('Restore:LaptopModel');
    }

    public function forceDelete(AuthUser $authUser, LaptopModel $laptopModel): bool
    {
        return $authUser->can('ForceDelete:LaptopModel');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LaptopModel');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LaptopModel');
    }

    public function replicate(AuthUser $authUser, LaptopModel $laptopModel): bool
    {
        return $authUser->can('Replicate:LaptopModel');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LaptopModel');
    }
}
