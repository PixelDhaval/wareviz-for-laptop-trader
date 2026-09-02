<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Processor;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ProcessorPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Processor');
    }

    public function view(AuthUser $authUser, Processor $processor): bool
    {
        return $authUser->can('View:Processor');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Processor');
    }

    public function update(AuthUser $authUser, Processor $processor): bool
    {
        return $authUser->can('Update:Processor');
    }

    public function delete(AuthUser $authUser, Processor $processor): bool
    {
        return $authUser->can('Delete:Processor');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Processor');
    }

    public function restore(AuthUser $authUser, Processor $processor): bool
    {
        return $authUser->can('Restore:Processor');
    }

    public function forceDelete(AuthUser $authUser, Processor $processor): bool
    {
        return $authUser->can('ForceDelete:Processor');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Processor');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Processor');
    }

    public function replicate(AuthUser $authUser, Processor $processor): bool
    {
        return $authUser->can('Replicate:Processor');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Processor');
    }
}
