<?php

use App\Filament\Resources\SalesOrders\Pages\EditSalesOrder;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Account;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function userWithRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('allows only internal roles to access the management panel', function (string $role, bool $canAccess): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = userWithRole($role);
    $panel = Filament::getPanel('management');

    expect($user->canAccessPanel($panel))->toBe($canAccess);
})->with([
    'super admin' => ['super-admin', true],
    'master data' => ['master-data', true],
    'account manager' => ['account-manager', true],
    'order manager' => ['order-manager', true],
    'vendor user' => ['vendor-user', false],
]);

it('seeds the role and permission matrix idempotently', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::query()->count())->toBe(5)
        ->and(Permission::query()->count())->toBe(23);
});

it('limits an account manager to assigned account details and actions', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $accountManager = userWithRole('account-manager');
    $assignedAccount = Account::factory()->create();
    $unassignedAccount = Account::factory()->create();
    $accountManager->managedAccounts()->attach($assignedAccount);

    expect(Gate::forUser($accountManager)->allows('viewAny', Account::class))->toBeTrue()
        ->and(Gate::forUser($accountManager)->allows('view', $assignedAccount))->toBeTrue()
        ->and(Gate::forUser($accountManager)->allows('update', $assignedAccount))->toBeTrue()
        ->and(Gate::forUser($accountManager)->allows('view', $unassignedAccount))->toBeFalse()
        ->and(Gate::forUser($accountManager)->allows('update', $unassignedAccount))->toBeFalse()
        ->and(Gate::forUser($accountManager)->allows('create', Account::class))->toBeFalse()
        ->and(Gate::forUser($accountManager)->allows('delete', $assignedAccount))->toBeFalse();
});

it('limits an account manager to assigned sales order details and actions', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $accountManager = userWithRole('account-manager');
    $assignedAccount = Account::factory()->create();
    $unassignedAccount = Account::factory()->create();
    $assignedOrder = SalesOrder::factory()->for($assignedAccount)->create();
    $unassignedOrder = SalesOrder::factory()->for($unassignedAccount)->create();
    $accountManager->managedAccounts()->attach($assignedAccount);

    expect(Gate::forUser($accountManager)->allows('viewAny', SalesOrder::class))->toBeTrue()
        ->and(Gate::forUser($accountManager)->allows('view', $assignedOrder))->toBeTrue()
        ->and(Gate::forUser($accountManager)->allows('update', $assignedOrder))->toBeTrue()
        ->and(Gate::forUser($accountManager)->allows('view', $unassignedOrder))->toBeFalse()
        ->and(Gate::forUser($accountManager)->allows('update', $unassignedOrder))->toBeFalse()
        ->and(Gate::forUser($accountManager)->allows('create', SalesOrder::class))->toBeFalse()
        ->and(Gate::forUser($accountManager)->allows('delete', $assignedOrder))->toBeFalse();
});

it('allows an order manager to manage every order but no other resource', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $orderManager = userWithRole('order-manager');
    $account = Account::factory()->create();
    $order = SalesOrder::factory()->for($account)->create();

    expect(Gate::forUser($orderManager)->allows('viewAny', SalesOrder::class))->toBeTrue()
        ->and(Gate::forUser($orderManager)->allows('view', $order))->toBeTrue()
        ->and(Gate::forUser($orderManager)->allows('create', SalesOrder::class))->toBeTrue()
        ->and(Gate::forUser($orderManager)->allows('update', $order))->toBeTrue()
        ->and(Gate::forUser($orderManager)->allows('delete', $order))->toBeTrue()
        ->and(Gate::forUser($orderManager)->allows('viewAny', Account::class))->toBeFalse()
        ->and(Gate::forUser($orderManager)->allows('viewAny', Product::class))->toBeFalse()
        ->and(Gate::forUser($orderManager)->allows('viewAny', User::class))->toBeFalse();
});

it('allows master data and super admin users to manage every current resource', function (string $role): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = userWithRole($role);
    $account = Account::factory()->create();
    $order = SalesOrder::factory()->for($account)->create();
    $product = Product::factory()->create();
    $managedUser = User::factory()->create();

    expect(Gate::forUser($user)->allows('update', $account))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $account))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $order))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $order))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $product))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $product))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $managedUser))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $managedUser))->toBeTrue();
})->with(['master-data', 'super-admin']);

it('enforces assigned account isolation on Filament record pages', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $accountManager = userWithRole('account-manager');
    $assignedAccount = Account::factory()->create();
    $unassignedAccount = Account::factory()->create();
    $assignedOrder = SalesOrder::factory()->for($assignedAccount)->create();
    $unassignedOrder = SalesOrder::factory()->for($unassignedAccount)->create();
    $accountManager->managedAccounts()->attach($assignedAccount);

    $this->actingAs($accountManager)
        ->get(route('filament.management.resources.accounts.index'))
        ->assertOk();
    $this->get(route('filament.management.resources.accounts.view', $assignedAccount))
        ->assertOk();
    $this->get(route('filament.management.resources.accounts.view', $unassignedAccount))
        ->assertForbidden();
    $this->get(route('filament.management.resources.sales-orders.index'))
        ->assertOk();
    $this->get(route('filament.management.resources.sales-orders.edit', $assignedOrder))
        ->assertOk();
    $this->get(route('filament.management.resources.sales-orders.edit', $unassignedOrder))
        ->assertForbidden();
});

it('rejects vendor users from the management panel', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $vendor = userWithRole('vendor-user');

    $this->actingAs($vendor)
        ->get(route('filament.management.pages.dashboard'))
        ->assertForbidden();
});

it('saves multiple managed account assignments from the Filament user form', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $masterDataUser = userWithRole('master-data');
    $accountManager = userWithRole('account-manager');
    $accounts = Account::factory()->count(2)->create();
    $accountManagerRole = Role::findByName('account-manager');
    $this->actingAs($masterDataUser);

    Livewire::test(EditUser::class, ['record' => $accountManager->getRouteKey()])
        ->fillForm([
            'roles' => [$accountManagerRole->getKey()],
            'managedAccounts' => $accounts->modelKeys(),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($accountManager->fresh()->managedAccounts()->pluck('accounts.id')->all())
        ->toEqualCanonicalizing($accounts->modelKeys());
});

it('rejects moving an assigned order to an unassigned account', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $accountManager = userWithRole('account-manager');
    $assignedAccount = Account::factory()->create();
    $unassignedAccount = Account::factory()->create();
    $order = SalesOrder::factory()->for($assignedAccount)->create();
    $accountManager->managedAccounts()->attach($assignedAccount);
    $this->actingAs($accountManager);

    Livewire::test(EditSalesOrder::class, ['record' => $order->getRouteKey()])
        ->fillForm(['account_id' => $unassignedAccount->getKey()])
        ->call('save')
        ->assertHasFormErrors(['account_id']);

    expect($order->fresh()->account_id)->toBe($assignedAccount->getKey());
});
