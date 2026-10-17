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
        $createAccount = Permission::create(['name' => 'create-account']);
        $deleteAccount = Permission::create(['name' => 'delete-account']);
        $updateAccount = Permission::create(['name' => 'update-account']);

        $createUser = Permission::create(['name' => 'create-user']);
        $deleteUser = Permission::create(['name' => 'delete-user']);
        $updateUser = Permission::create(['name' => 'update-user']);

        $updateOrder = Permission::create(['name' => 'update-order']);
        $deleteOrder = Permission::create(['name' => 'delete-order']);
        $createOrder = Permission::create(['name' => 'create-order']);

        $masterData = Role::create(['name' => 'master-data']);

        $masterData->givePermissionTo([
            $createAccount,
            $deleteAccount,
            $updateAccount,
            $createUser,
            $deleteUser,
            $updateUser
        ]);

        $accountManager = Role::create(['name' => 'account-manager']);
        $accountManager->givePermissionTo($updateOrder);

        $orderManager = Role::create(['name' => 'order-manager']);
        $orderManager->givePermissionTo([
            $createOrder,
            $updateOrder,
            $deleteOrder
        ]);

        $vendor = Role::create(['name' => 'vendor-user']);
        $vendor->givePermissionTo([
            $createOrder,
            $updateOrder
        ]);

        $superAdmin = Role::create(['name' => 'super-admin']);
        $superAdmin->givePermissionTo([
            $createAccount,
            $deleteAccount,
            $updateAccount,
            $createUser,
            $deleteUser,
            $updateUser,
            $createOrder,
            $deleteOrder,
            $updateOrder
        ]);
    }
}
