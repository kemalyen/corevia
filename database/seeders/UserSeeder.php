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

        $users = User::factory(10)->create()->assignRole('account-manager');

        Account::factory(50)
            ->recycle($users)
            ->has(
                User::factory()
            )->create()
            ->each(function ($account) {
                $account->users->each(function ($user) {
                    $user->assignRole('vendor-user');
                });
            });

        User::factory(2)->create()->assignRole('master-data');

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'test@example.com',
        ])->assignRole('super-admin');
    }
}
