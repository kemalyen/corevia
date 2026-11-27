<?php

use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\Accounts\Pages\ManageAccountProductPrices;
use App\Filament\Resources\Accounts\Pages\ManageAccountSalesOrders;
use App\Filament\Resources\Accounts\Pages\ManageAccountUsers;
use App\Models\Account;
use App\Models\AccountProductPrice;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function accountPagesAdministrator(string $role = 'master-data'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('links each account relationship page from the table and account sub-navigation', function (): void {
    $administrator = accountPagesAdministrator();
    $account = Account::factory()->create(['name' => 'Northwind']);
    $this->actingAs($administrator);

    Livewire::test(ListAccounts::class)
        ->assertSee(AccountResource::getUrl('users', ['record' => $account]))
        ->assertSee(AccountResource::getUrl('sales-orders', ['record' => $account]))
        ->assertSee(AccountResource::getUrl('product-prices', ['record' => $account]));

    $this->get(AccountResource::getUrl('view', ['record' => $account]))
        ->assertOk()
        ->assertSee(AccountResource::getUrl('users', ['record' => $account]))
        ->assertSee(AccountResource::getUrl('sales-orders', ['record' => $account]))
        ->assertSee(AccountResource::getUrl('product-prices', ['record' => $account]));
});

it('renders paginated account-scoped users', function (): void {
    $administrator = accountPagesAdministrator();
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $users = collect(range(1, 11))->map(fn (int $number): User => User::factory()->for($account)->create([
        'name' => sprintf('Account user %02d', $number),
    ]));
    $otherUser = User::factory()->for($otherAccount)->create();
    $this->actingAs($administrator);

    Livewire::test(ManageAccountUsers::class, ['record' => $account->getRouteKey()])
        ->assertCountTableRecords(11)
        ->assertCanNotSeeTableRecords([$otherUser])
        ->set('tableRecordsPerPage', 10)
        ->call('gotoPage', 2, 'manageAccountUsersPage')
        ->assertCanSeeTableRecords([$users->last()]);
});

it('scopes sales orders and product price overrides to the account', function (): void {
    $administrator = accountPagesAdministrator();
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $order = SalesOrder::factory()->for($account)->create();
    $otherOrder = SalesOrder::factory()->for($otherAccount)->create();
    $product = Product::factory()->create();
    $price = AccountProductPrice::factory()->for($account)->for($product)->create();
    $otherPrice = AccountProductPrice::factory()->for($otherAccount)->for($product)->create([
        'currency' => 'USD',
    ]);
    $this->actingAs($administrator);

    Livewire::test(ManageAccountSalesOrders::class, ['record' => $account->getRouteKey()])
        ->assertCanSeeTableRecords([$order])
        ->assertCanNotSeeTableRecords([$otherOrder]);

    Livewire::test(ManageAccountProductPrices::class, ['record' => $account->getRouteKey()])
        ->assertCanSeeTableRecords([$price])
        ->assertCanNotSeeTableRecords([$otherPrice]);
});

it('enforces direct page access using existing account and related permissions', function (): void {
    $accountManager = accountPagesAdministrator('account-manager');
    $assignedAccount = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $accountManager->managedAccounts()->attach($assignedAccount);

    $this->actingAs($accountManager)
        ->get(AccountResource::getUrl('sales-orders', ['record' => $assignedAccount]))
        ->assertOk();
    $this->get(AccountResource::getUrl('product-prices', ['record' => $assignedAccount]))
        ->assertOk();
    $this->get(AccountResource::getUrl('users', ['record' => $assignedAccount]))
        ->assertForbidden();
    $this->get(AccountResource::getUrl('sales-orders', ['record' => $otherAccount]))
        ->assertForbidden();
    $this->get(AccountResource::getUrl('product-prices', ['record' => $otherAccount]))
        ->assertForbidden();
});

it('hides and forbids sales and product price pages for supplier accounts', function (): void {
    $administrator = accountPagesAdministrator();
    $supplier = Account::factory()->supplier()->create();
    $this->actingAs($administrator);

    Livewire::test(ListAccounts::class)
        ->assertDontSee(AccountResource::getUrl('sales-orders', ['record' => $supplier]))
        ->assertDontSee(AccountResource::getUrl('product-prices', ['record' => $supplier]));

    $this->get(AccountResource::getUrl('sales-orders', ['record' => $supplier]))
        ->assertForbidden();
    $this->get(AccountResource::getUrl('product-prices', ['record' => $supplier]))
        ->assertForbidden();
});

it('creates account price overrides through the scoped relationship', function (): void {
    $administrator = accountPagesAdministrator();
    $account = Account::factory()->create();
    $product = Product::factory()->create();
    $this->actingAs($administrator);

    Livewire::test(ManageAccountProductPrices::class, ['record' => $account->getRouteKey()])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'product_id' => $product->getKey(),
            'amount' => '8.5000',
            'currency' => 'GBP',
            'unit_of_measure' => 'EA',
        ])
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('account_product_prices', [
        'account_id' => $account->getKey(),
        'product_id' => $product->getKey(),
        'amount' => 8.5,
        'currency' => 'GBP',
        'unit_of_measure' => 'EA',
    ]);
});

it('rejects attempts to edit another account price through the scoped table', function (): void {
    $administrator = accountPagesAdministrator();
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $product = Product::factory()->create();
    $otherPrice = AccountProductPrice::factory()->for($otherAccount)->for($product)->create();
    $this->actingAs($administrator);

    expect(fn () => Livewire::test(ManageAccountProductPrices::class, ['record' => $account->getRouteKey()])
        ->callAction(TestAction::make(EditAction::class)->table($otherPrice), [
            'amount' => '1.0000',
        ]))->toThrow(ActionNotResolvableException::class);

    expect($otherPrice->fresh()->amount)->not->toBe('1.0000');
});
