<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Buyer;
use Illuminate\Auth\Access\HandlesAuthorization;

class BuyerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Buyer');
    }

    public function view(AuthUser $authUser, Buyer $buyer): bool
    {
        return $authUser->can('View:Buyer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Buyer');
    }

    public function update(AuthUser $authUser, Buyer $buyer): bool
    {
        return $authUser->can('Update:Buyer');
    }

    public function delete(AuthUser $authUser, Buyer $buyer): bool
    {
        return $authUser->can('Delete:Buyer');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Buyer');
    }

    public function restore(AuthUser $authUser, Buyer $buyer): bool
    {
        return $authUser->can('Restore:Buyer');
    }

    public function forceDelete(AuthUser $authUser, Buyer $buyer): bool
    {
        return $authUser->can('ForceDelete:Buyer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Buyer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Buyer');
    }

    public function replicate(AuthUser $authUser, Buyer $buyer): bool
    {
        return $authUser->can('Replicate:Buyer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Buyer');
    }

}