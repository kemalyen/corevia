<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'access-management-panel',
            'view-any-account',
            'view-account',
            'create-account',
            'update-account',
            'delete-account',
            'manage-all-accounts',
            'view-any-user',
            'view-user',
            'create-user',
            'update-user',
            'delete-user',
            'view-any-product',
            'view-product',
            'create-product',
            'update-product',
            'delete-product',
            'view-any-order',
            'view-order',
            'create-order',
            'update-order',
            'delete-order',
            'manage-all-orders',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // DatabaseSeeder disables model events, so Spatie cannot invalidate this cache automatically.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $masterData = Role::findOrCreate('master-data', 'web');
        $masterData->syncPermissions($permissions);

        $accountManager = Role::findOrCreate('account-manager', 'web');
        $accountManager->syncPermissions([
            'access-management-panel',
            'view-any-account',
            'view-account',
            'update-account',
            'view-any-order',
            'view-order',
            'update-order',
        ]);

        $orderManager = Role::findOrCreate('order-manager', 'web');
        $orderManager->syncPermissions([
            'access-management-panel',
            'view-any-order',
            'view-order',
            'create-order',
            'update-order',
            'delete-order',
            'manage-all-orders',
        ]);

        $vendor = Role::findOrCreate('vendor-user', 'web');
        $vendor->syncPermissions([
            'view-any-product',
            'view-product',
            'view-any-order',
            'view-order',
            'create-order',
            'update-order',
        ]);

        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $superAdmin->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
