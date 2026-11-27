<?php

use App\Enums\AccountProductAccessMode;
use App\Filament\Resources\Accounts\Pages\EditAccount;
use App\Filament\Resources\Accounts\Pages\ManageAccountProductPrices;
use App\Models\Account;
use App\Models\AccountProductPrice;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

function catalogueAdministrator(string $role = 'master-data'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('defaults newly created accounts to restricted access without grants', function (): void {
    $account = Account::query()->create([
        'account_number' => 'NEW-RESTRICTED',
        'name' => 'New restricted account',
        'account_type' => 'vendor',
        'account_status' => 'active',
    ]);

    expect($account->product_access_mode)->toBe(AccountProductAccessMode::RESTRICTED)
        ->and($account->permittedProducts()->exists())->toBeFalse()
        ->and($account->permittedProductCategories()->exists())->toBeFalse();
});

it('allows authorised staff to configure restricted product and category grants', function (): void {
    $administrator = catalogueAdministrator();
    $account = Account::factory()->create();
    $product = Product::factory()->create();
    $category = ProductCategory::factory()->create();
    $this->actingAs($administrator);

    Livewire::test(EditAccount::class, ['record' => $account->getRouteKey()])
        ->fillForm([
            'product_access_mode' => AccountProductAccessMode::RESTRICTED->value,
            'permittedProducts' => [$product->getKey()],
            'permittedProductCategories' => [$category->getKey()],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($account->fresh()->permittedProducts()->pluck('products.id')->all())->toBe([$product->getKey()])
        ->and($account->fresh()->permittedProductCategories()->pluck('product_categories.id')->all())->toBe([$category->getKey()]);
});

it('enforces assigned-account permissions for catalogue pricing administration', function (): void {
    $accountManager = catalogueAdministrator('account-manager');
    $assigned = Account::factory()->create();
    $unassigned = Account::factory()->create();
    $product = Product::factory()->create();
    $assignedPrice = AccountProductPrice::factory()->for($assigned)->for($product)->create();
    $unassignedPrice = AccountProductPrice::factory()->for($unassigned)->for($product)->create([
        'currency' => 'USD',
    ]);
    $accountManager->managedAccounts()->attach($assigned);

    expect(Gate::forUser($accountManager)->allows('update', $assignedPrice))->toBeTrue()
        ->and(Gate::forUser($accountManager)->allows('delete', $assignedPrice))->toBeTrue()
        ->and(Gate::forUser($accountManager)->allows('update', $unassignedPrice))->toBeFalse()
        ->and(Gate::forUser($accountManager)->allows('delete', $unassignedPrice))->toBeFalse();

    $this->actingAs($accountManager);
    Livewire::test(ManageAccountProductPrices::class, [
        'record' => $assigned->getRouteKey(),
    ])->assertOk();

    $this->get(route('filament.management.resources.accounts.edit', $unassigned))->assertForbidden();
});
