<?php

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Livewire\Portal\Login;
use App\Models\Account;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

function portalAccessUser(Account $account, bool $active = true, bool $withRole = true): User
{
    $user = User::factory()->for($account)->create(['is_active' => $active]);

    if ($withRole) {
        $user->assignRole('vendor-user');
    }

    return $user;
}

it('redirects guests to the vendor sign in page', function (): void {
    $this->get(route('portal.dashboard'))
        ->assertRedirect(route('login'));
});

it('allows an active authorised vendor member to access the portal', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $account = Account::factory()->create([
        'account_type' => AccountType::VENDOR,
        'account_status' => AccountStatus::ACTIVE,
    ]);
    $user = portalAccessUser($account);

    $this->actingAs($user)->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee($account->name);
});

it('authenticates an authorised vendor through the existing web guard', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $account = Account::factory()->create([
        'account_type' => AccountType::VENDOR,
        'account_status' => AccountStatus::ACTIVE,
    ]);
    $user = portalAccessUser($account);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(route('portal.dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('does not keep a supplier user authenticated through the vendor sign in', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $account = Account::factory()->create([
        'account_type' => AccountType::SUPPLIER,
        'account_status' => AccountStatus::ACTIVE,
    ]);
    $user = portalAccessUser($account);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('authenticate')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

it('forbids unsupported or inactive memberships', function (AccountType $type, AccountStatus $status, bool $userActive, bool $withRole): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $account = Account::factory()->create(['account_type' => $type, 'account_status' => $status]);
    $user = portalAccessUser($account, $userActive, $withRole);

    $this->actingAs($user)->get(route('portal.dashboard'))->assertForbidden();
})->with([
    'supplier account' => [AccountType::SUPPLIER, AccountStatus::ACTIVE, true, true],
    'inactive vendor account' => [AccountType::VENDOR, AccountStatus::INACTIVE, true, true],
    'inactive user' => [AccountType::VENDOR, AccountStatus::ACTIVE, false, true],
    'revoked vendor role' => [AccountType::VENDOR, AccountStatus::ACTIVE, true, false],
]);

it('forbids a role without an account membership', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('vendor-user');

    $this->actingAs($user)->get(route('portal.dashboard'))->assertForbidden();
});
