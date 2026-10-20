<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserSeeder;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\seed;

it('seeds identifiable account managers with multiple assigned accounts', function (): void {
    seed([
        RolesAndPermissionsSeeder::class,
        UserSeeder::class,
    ]);

    $accountManagers = User::role('account-manager')
        ->get()
        ->keyBy('email');

    expect($accountManagers->keys()->sort()->values()->all())->toBe([
        'account.manager.one@corevia.test',
        'account.manager.three@corevia.test',
        'account.manager.two@corevia.test',
    ]);

    expect($accountManagers['account.manager.one@corevia.test']->managedAccounts)->toHaveCount(3)
        ->and($accountManagers['account.manager.two@corevia.test']->managedAccounts)->toHaveCount(3)
        ->and($accountManagers['account.manager.three@corevia.test']->managedAccounts)->toHaveCount(3);

    assertDatabaseCount('account_manager_to_account', 9);
});
