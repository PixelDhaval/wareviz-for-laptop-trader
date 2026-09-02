<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Laptop;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LaptopPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Laptop');
    }

    public function view(AuthUser $authUser, Laptop $laptop): bool
    {
        return $authUser->can('View:Laptop');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Laptop');
    }

    public function update(AuthUser $authUser, Laptop $laptop): bool
    {
        return $authUser->can('Update:Laptop');
    }

    public function delete(AuthUser $authUser, Laptop $laptop): bool
    {
        return $authUser->can('Delete:Laptop');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Laptop');
    }

    public function restore(AuthUser $authUser, Laptop $laptop): bool
    {
        return $authUser->can('Restore:Laptop');
    }

    public function forceDelete(AuthUser $authUser, Laptop $laptop): bool
    {
        return $authUser->can('ForceDelete:Laptop');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Laptop');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Laptop');
    }

    public function replicate(AuthUser $authUser, Laptop $laptop): bool
    {
        return $authUser->can('Replicate:Laptop');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Laptop');
    }
}
