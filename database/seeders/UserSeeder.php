<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accountManagerRole = Role::findByName('account-manager');
        $vendorUserRole = Role::findByName('vendor-user');
        $orderManagerRole = Role::findByName('order-manager');
        $superAdminRole = Role::findByName('super-admin');
        $masterDataRole = Role::findByName('master-data');

        $accountManagers = User::factory()
            ->count(3)
            ->sequence(
                [
                    'name' => 'Account Manager One',
                    'email' => 'account.manager.one@corevia.test',
                ],
                [
                    'name' => 'Account Manager Two',
                    'email' => 'account.manager.two@corevia.test',
                ],
                [
                    'name' => 'Account Manager Three',
                    'email' => 'account.manager.three@corevia.test',
                ],
            )
            ->create()
            ->each(function (User $user) use ($accountManagerRole): void {
                $user->assignRole($accountManagerRole);
            });

        $accounts = Account::factory(5)
            ->create()
            ->each(function (Account $account) use ($vendorUserRole): void {
                $user = User::factory()->create();
                $user->assignRole($vendorUserRole);
                $account->users()->save($user);
            });

        $accountManagers[0]->managedAccounts()->sync($accounts->take(3)->modelKeys());
        $accountManagers[1]->managedAccounts()->sync($accounts->slice(1, 3)->modelKeys());
        $accountManagers[2]->managedAccounts()->sync($accounts->slice(2, 3)->modelKeys());

        User::factory(2)->create()->each(function (User $user) use ($masterDataRole): void {
            $user->assignRole($masterDataRole);
        });

        User::factory(2)->create()->each(function (User $user) use ($orderManagerRole): void {
            $user->assignRole($orderManagerRole);
        });

        $adminUser = User::factory()->create([
            'name' => 'Admin',
            'email' => 'test@example.com',
        ]);
        $adminUser->assignRole($superAdminRole);
    }
}
