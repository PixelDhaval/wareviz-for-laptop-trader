<?php

namespace Database\Seeders;

use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
            '--option' => 'permissions',
            '--no-interaction' => true,
        ]);

        // The activity timeline resource ships in a third-party package
        // (bokshorn-it/filament-activity-timeline), so `shield:generate`
        // never discovers it and these permissions have to be created by
        // hand — see App\Policies\ActivityPolicy.
        foreach (['ViewAny:Activity', 'View:Activity'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Utils::createRole()->syncPermissions(Utils::getPermissionModel()::pluck('id'));
    }
}
