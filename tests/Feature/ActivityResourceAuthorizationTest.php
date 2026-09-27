<?php

use App\Models\Supplier;
use App\Models\User;
use BokshornIt\FilamentActivityTimeline\Resources\Pages\ListActivities;
use Livewire\Livewire;

test('a super_admin can view the activity log', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Supplier::factory()->create();

    Livewire::test(ListActivities::class)->assertOk();
});

test('a user without the activity permission cannot view the activity log', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ListActivities::class)->assertForbidden();
});
