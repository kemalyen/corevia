<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $accountManegerRole = \Spatie\Permission\Models\Role::where('name', 'account-manager')->first();
        $vendorUserRole = \Spatie\Permission\Models\Role::where('name', 'vendor-user')->first();
        $superAdminRole = \Spatie\Permission\Models\Role::where('name', 'super-admin')->first();
        $masterDataRole = \Spatie\Permission\Models\Role::where('name', 'master-data')->first();

        $users = User::factory(10)->create()
            ->each(function ($user) use ($accountManegerRole) {
                $user->assignRole($accountManegerRole);
            });

        Account::factory(5)
            ->recycle($users)
            ->create()
            ->each(function ($account) use ($vendorUserRole) {
                $user = User::factory()->create();
                $user->assignRole($vendorUserRole);
                $account->users()->save($user);
            });

        User::factory(2)->create()->each(function ($user) use ($masterDataRole) {
            $user->assignRole($masterDataRole);
        });

        $adminUser = User::factory()->create([
            'name' => 'Admin',
            'email' => 'test@example.com',
        ]);
        $adminUser->assignRole($superAdminRole);
    }
}
