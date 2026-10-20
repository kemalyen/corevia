<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\seed;

it('assigns permissions when the parent seeder disables model events', function (): void {
    Model::withoutEvents(function (): void {
        seed(RolesAndPermissionsSeeder::class);
    });

    $masterData = Role::findByName('master-data', 'web');
    $accountManager = Role::findByName('account-manager', 'web');

    expect($masterData->hasPermissionTo('access-management-panel'))->toBeTrue()
        ->and($accountManager->hasPermissionTo('access-management-panel'))->toBeTrue()
        ->and($accountManager->hasPermissionTo('update-account'))->toBeTrue();
});
