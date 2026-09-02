<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

test('a user without the required permissions cannot view the users list', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('filament.admin.resources.users.index'))
        ->assertForbidden();
});

test('a super_admin can view the users list', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $this->get(route('filament.admin.resources.users.index'))
        ->assertOk();
});

test('a super_admin can create a user with a role', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password',
            'roles' => [Role::where('name', 'super_admin')->value('id')],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($user->name)->toBe('Jane Doe')
        ->and($user->hasRole('super_admin'))->toBeTrue();
});
