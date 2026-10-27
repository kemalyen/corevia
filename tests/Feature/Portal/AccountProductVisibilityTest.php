<?php

use App\Enums\AccountProductAccessMode;
use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\AccountProductPrice;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Portal\Catalogue\AccountProductVisibility;
use App\Portal\Queries\VendorCatalogueQuery;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

function visibilityVendor(Account $account): User
{
    $user = User::factory()->for($account)->create();
    $user->assignRole('vendor-user');

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('returns every product for all-products accounts', function (): void {
    $account = Account::factory()->create(['product_access_mode' => AccountProductAccessMode::ALL]);
    $products = Product::factory()->count(3)->create();

    $visibleIds = app(AccountProductVisibility::class)->query($account)->pluck('id');

    expect($visibleIds->all())->toEqualCanonicalizing($products->modelKeys());
});

it('returns the union of individual category and descendant grants', function (): void {
    $account = Account::factory()->create(['product_access_mode' => AccountProductAccessMode::RESTRICTED]);
    $root = ProductCategory::factory()->create(['name' => 'Root']);
    $child = ProductCategory::factory()->create(['name' => 'Child', 'parent_id' => $root->getKey()]);
    $grandchild = ProductCategory::factory()->create(['name' => 'Grandchild', 'parent_id' => $child->getKey()]);
    $rootProduct = Product::factory()->create();
    $childProduct = Product::factory()->create();
    $grandchildProduct = Product::factory()->create();
    $individualProduct = Product::factory()->create();
    $hiddenProduct = Product::factory()->create();
    $root->products()->attach($rootProduct);
    $child->products()->attach($childProduct);
    $grandchild->products()->attach($grandchildProduct);
    $account->permittedProductCategories()->attach($root);
    $account->permittedProducts()->attach($individualProduct);

    $visibleIds = app(AccountProductVisibility::class)->query($account)->pluck('id');

    expect($visibleIds->all())->toEqualCanonicalizing([
        $rootProduct->getKey(),
        $childProduct->getKey(),
        $grandchildProduct->getKey(),
        $individualProduct->getKey(),
    ])->not->toContain($hiddenProduct->getKey());
});

it('returns no products for restricted accounts without grants and isolates grants by account', function (): void {
    $grantedAccount = Account::factory()->create(['product_access_mode' => AccountProductAccessMode::RESTRICTED]);
    $otherAccount = Account::factory()->create(['product_access_mode' => AccountProductAccessMode::RESTRICTED]);
    $product = Product::factory()->create();
    $grantedAccount->permittedProducts()->attach($product);
    $visibility = app(AccountProductVisibility::class);

    expect($visibility->query($grantedAccount)->pluck('id')->all())->toBe([$product->getKey()])
        ->and($visibility->query($otherAccount)->exists())->toBeFalse();
});

it('does not treat an account-specific price as a product visibility grant', function (): void {
    $account = Account::factory()->create(['product_access_mode' => AccountProductAccessMode::RESTRICTED]);
    $product = Product::factory()->create();
    AccountProductPrice::factory()->for($account)->for($product)->create();

    expect(app(AccountProductVisibility::class)->query($account)->exists())->toBeFalse();
});

it('enforces visibility in asynchronous search and direct product lookup', function (): void {
    $account = Account::factory()->create([
        'account_type' => AccountType::VENDOR,
        'account_status' => AccountStatus::ACTIVE,
        'product_access_mode' => AccountProductAccessMode::RESTRICTED,
    ]);
    $user = visibilityVendor($account);
    $visible = Product::factory()->create(['sku' => 'ACCESS-YES', 'name' => 'Allowed widget']);
    $hidden = Product::factory()->create(['sku' => 'ACCESS-NO', 'name' => 'Hidden widget']);
    $account->permittedProducts()->attach($visible);
    $catalogue = app(VendorCatalogueQuery::class);

    $result = $catalogue->search($user, 'access');

    expect(collect($result['results'])->pluck('id')->all())->toBe([$visible->getKey()])
        ->and($catalogue->findOrderableForUser($user, $visible->getKey())?->is($visible))->toBeTrue()
        ->and($catalogue->findOrderableForUser($user, $hidden->getKey()))->toBeNull();
});

it('follows current hierarchy changes without copying descendant grants', function (): void {
    $account = Account::factory()->create(['product_access_mode' => AccountProductAccessMode::RESTRICTED]);
    $grantedRoot = ProductCategory::factory()->create();
    $otherRoot = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->create(['parent_id' => $grantedRoot->getKey()]);
    $product = Product::factory()->create();
    $child->products()->attach($product);
    $account->permittedProductCategories()->attach($grantedRoot);
    $visibility = app(AccountProductVisibility::class);

    expect($visibility->query($account)->whereKey($product)->exists())->toBeTrue();

    $child->update(['parent_id' => $otherRoot->getKey()]);

    expect($visibility->query($account)->whereKey($product)->exists())->toBeFalse();
});

it('prevents product category hierarchy cycles', function (): void {
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->create(['parent_id' => $root->getKey()]);

    expect(fn () => $root->update(['parent_id' => $child->getKey()]))
        ->toThrow(ValidationException::class, 'A product category cannot be its own ancestor.');
});
