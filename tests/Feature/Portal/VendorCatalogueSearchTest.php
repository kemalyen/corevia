<?php

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Livewire\Portal\Orders\Create;
use App\Models\Account;
use App\Models\Product;
use App\Models\User;
use App\Portal\Queries\VendorCatalogueQuery;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Livewire\Livewire;

function catalogueSearchVendor(?Account $account = null): User
{
    $account ??= Account::factory()->withAllProducts()->create([
        'account_type' => AccountType::VENDOR,
        'account_status' => AccountStatus::ACTIVE,
    ]);
    $user = User::factory()->for($account)->create();
    $user->assignRole('vendor-user');

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('does not preload the product catalogue into the create order page', function (): void {
    $vendor = catalogueSearchVendor();
    Product::factory()->create([
        'sku' => 'NOT-IN-INITIAL-PAYLOAD',
        'name' => 'Server search only product',
    ]);

    $this->actingAs($vendor)
        ->get(route('portal.orders.create'))
        ->assertOk()
        ->assertDontSee('NOT-IN-INITIAL-PAYLOAD')
        ->assertDontSee('Server search only product');
});

it('searches the catalogue only for an authorised active vendor account', function (): void {
    $vendor = catalogueSearchVendor();
    Product::factory()->create(['sku' => 'AB-200', 'name' => 'Secondary match']);
    $exact = Product::factory()->create(['sku' => 'AB', 'name' => 'Exact match']);
    $nameMatch = Product::factory()->create(['sku' => 'ZZ-100', 'name' => 'Abacus']);
    Product::factory()->create(['sku' => 'ZZ-200', 'name' => 'Unrelated']);

    $result = app(VendorCatalogueQuery::class)->search($vendor, 'ab');

    expect($result['results'])->toHaveCount(3)
        ->and($result['results'][0]['id'])->toBe($exact->getKey())
        ->and(collect($result['results'])->pluck('id'))->toContain($nameMatch->getKey())
        ->and($result['has_more'])->toBeFalse();

    $supplierAccount = Account::factory()->create([
        'account_type' => AccountType::SUPPLIER,
        'account_status' => AccountStatus::ACTIVE,
    ]);
    $supplier = catalogueSearchVendor($supplierAccount);

    expect(fn (): array => app(VendorCatalogueQuery::class)->search($supplier, 'ab'))
        ->toThrow(AuthorizationException::class);
});

it('returns no more than twenty products and signals that refinement is needed', function (): void {
    $vendor = catalogueSearchVendor();
    Product::factory()
        ->count(25)
        ->sequence(fn (Sequence $sequence): array => [
            'sku' => sprintf('MATCH-%02d', $sequence->index),
            'name' => "Matching product {$sequence->index}",
        ])
        ->create();

    $result = app(VendorCatalogueQuery::class)->search($vendor, 'match');

    expect($result['results'])->toHaveCount(20)
        ->and($result['has_more'])->toBeTrue();
});

it('revalidates a selected product before adding it to the draft', function (): void {
    $vendor = catalogueSearchVendor();
    $product = Product::factory()->create([
        'sku' => 'VALID-1',
        'name' => 'Valid product',
        'price' => '12.50',
        'currency' => 'GBP',
    ]);
    $this->actingAs($vendor);

    $component = Livewire::test(Create::class)
        ->call('selectProduct', $product->getKey())
        ->assertSet('selectedProductId', $product->getKey())
        ->assertSet('selectedProductLabel', 'VALID-1 — Valid product');

    $product->delete();

    $component
        ->call('addLine')
        ->assertHasErrors(['selectedProductId'])
        ->assertSet('lines', []);
});

it('rejects a tampered product identifier during selection', function (): void {
    $vendor = catalogueSearchVendor();
    $this->actingAs($vendor);

    Livewire::test(Create::class)
        ->call('selectProduct', 999999)
        ->assertHasErrors(['selectedProductId'])
        ->assertSet('selectedProductId', null)
        ->assertSet('selectedProductLabel', null);
});

it('does not allow a visible product without valid pricing to be selected', function (): void {
    $vendor = catalogueSearchVendor();
    $product = Product::factory()->create(['price' => null, 'currency' => 'GBP']);
    $this->actingAs($vendor);

    Livewire::test(Create::class)
        ->call('selectProduct', $product->getKey())
        ->assertHasErrors(['selectedProductId'])
        ->assertSet('selectedProductId', null);
});

it('clears the selected product and draft when the active account changes', function (): void {
    $vendor = catalogueSearchVendor();
    $newAccount = Account::factory()->withAllProducts()->create([
        'account_type' => AccountType::VENDOR,
        'account_status' => AccountStatus::ACTIVE,
    ]);
    $product = Product::factory()->create([
        'sku' => 'SWITCH-1',
        'name' => 'Account switch product',
        'price' => '8.00',
        'currency' => 'GBP',
    ]);
    $this->actingAs($vendor);

    $component = Livewire::test(Create::class)
        ->call('selectProduct', $product->getKey())
        ->call('addLine')
        ->call('selectProduct', $product->getKey());

    $vendor->update(['account_id' => $newAccount->getKey()]);

    $component
        ->call('searchProducts', 'switch')
        ->assertSet('activeAccountId', $newAccount->getKey())
        ->assertSet('selectedProductId', null)
        ->assertSet('selectedProductLabel', null)
        ->assertSet('lines', []);
});
