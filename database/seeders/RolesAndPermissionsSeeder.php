<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        Permission::create(['name' => 'create account']);
        Permission::create(['name' => 'delete account']);
        Permission::create(['name' => 'update account']);

        Permission::create(['name' => 'create user']);
        Permission::create(['name' => 'delete user']);
        Permission::create(['name' => 'update user']);

        Permission::create(['name' => 'update order']);
        Permission::create(['name' => 'delete order']);
        Permission::create(['name' => 'create order']);

        $masterData = Role::create(['name' => 'master-data']);

        $masterData->givePermissionTo([
            'create account',
            'delete account',
            'update account',
            'create user',
            'delete user',
            'update user'
        ]);

        $accountManager = Role::create(['name' => 'account-manager']);
        $accountManager->givePermissionTo('update order');

        $orderManager = Role::create(['name' => 'order-manager']);
        $orderManager->givePermissionTo([
            'create order',
            'update order',
            'delete order'
        ]);

        $vendor = Role::create(['name' => 'vendor-user']);
        $vendor->givePermissionTo([
            'create order',
            'update order'
        ]);

        $accountManager = Role::create(['name' => 'super-admin']);
    }
}
