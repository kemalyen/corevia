<?php

use App\Enums\AccountProductAccessMode;
use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Portal\Assistant\VendorAssistantOperations;
use App\Portal\Assistant\VendorAssistantToolRegistry;
use App\Portal\Orders\VendorOrderDraft;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

function assistantOperationsVendor(?Account $account = null): User
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

it('returns only account-visible products and reports stock as unknown', function (): void {
    $account = Account::factory()->create([
        'account_type' => AccountType::VENDOR,
        'account_status' => AccountStatus::ACTIVE,
        'product_access_mode' => AccountProductAccessMode::RESTRICTED,
    ]);
    $user = assistantOperationsVendor($account);
    $visible = Product::factory()->create(['sku' => 'SAFE-1', 'name' => 'Safe widget', 'price' => '14.50', 'currency' => 'GBP']);
    $hidden = Product::factory()->create(['sku' => 'SAFE-2', 'name' => 'Safe hidden widget']);
    $account->permittedProducts()->attach($visible);
    $operations = app(VendorAssistantOperations::class);

    $search = $operations->searchProducts($user, $account, 'safe');
    $stock = $operations->stock($user, $account, $visible->getKey());

    expect($search['results'])->toHaveCount(1)
        ->and($search['results'][0]['id'])->toBe($visible->getKey());
    expect($stock['status'])->toBe('unknown')
        ->and($stock['quantity'])->toBeNull()
        ->and($stock['source_timestamp'])->toBeNull();

    expect(fn () => $operations->stock($user, $account, $hidden->getKey()))
        ->toThrow(AuthorizationException::class);
});

it('does not return orders belonging to another account', function (): void {
    $user = assistantOperationsVendor();
    $otherAccount = Account::factory()->create();
    $ownOrder = SalesOrder::factory()->for($user->account)->create();
    $otherOrder = SalesOrder::factory()->for($otherAccount)->create();
    $operations = app(VendorAssistantOperations::class);

    $orders = $operations->listOrders($user, $user->account, 20);

    expect(collect($orders['orders'])->pluck('id')->all())->toBe([$ownOrder->getKey()]);
    expect(fn () => $operations->order($user, $user->account, $otherOrder->getKey()))
        ->toThrow(ModelNotFoundException::class);
});

it('rejects unknown tools, unexpected arguments, and invalid quantities', function (): void {
    $user = assistantOperationsVendor();
    $registry = app(VendorAssistantToolRegistry::class);

    expect(fn () => $registry->execute('run_php', [], $user, $user->account))
        ->toThrow(DomainException::class);
    expect(fn () => $registry->execute('get_order_draft', ['account_id' => $user->account_id], $user, $user->account))
        ->toThrow(ValidationException::class);
    expect(fn () => $registry->execute('add_order_draft_line', ['product_id' => 1, 'quantity' => '0'], $user, $user->account))
        ->toThrow(ValidationException::class);
});

it('does not mutate the draft when product search is ambiguous', function (): void {
    $user = assistantOperationsVendor();
    Product::factory()->create(['sku' => 'WIDGET-1', 'name' => 'Widget one']);
    Product::factory()->create(['sku' => 'WIDGET-2', 'name' => 'Widget two']);
    $registry = app(VendorAssistantToolRegistry::class);

    $result = $registry->execute('search_products', ['query' => 'widget'], $user, $user->account);

    expect($result['results'])->toHaveCount(2)
        ->and(app(VendorOrderDraft::class)->current($user))->toBe([]);
});

it('shares assistant draft changes with the traditional order form', function (): void {
    $user = assistantOperationsVendor();
    $product = Product::factory()->create(['price' => '9.25', 'currency' => 'GBP']);
    $this->actingAs($user);

    app(VendorAssistantToolRegistry::class)->execute('add_order_draft_line', [
        'product_id' => $product->getKey(),
        'quantity' => '3',
    ], $user, $user->account);

    $this->get(route('portal.orders.create'))
        ->assertOk()
        ->assertSee($product->sku)
        ->assertSee('27.75');
});
