<?php

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\ErpSyncState;
use App\Enums\OrderOrigin;
use App\Livewire\Portal\Orders\Create;
use App\Models\Account;
use App\Models\Product;
use App\Models\User;
use App\Portal\Actions\SubmitVendorOrder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

function orderSubmissionVendor(): User
{
    $account = Account::factory()->create([
        'account_type' => AccountType::VENDOR,
        'account_status' => AccountStatus::ACTIVE,
    ]);
    $user = User::factory()->for($account)->create();
    $user->assignRole('vendor-user');

    return $user;
}

it('uses server-side product pricing and creates a pending portal request', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = orderSubmissionVendor();
    $product = Product::factory()->create(['price' => '12.50', 'currency' => 'GBP']);
    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('customerReference', 'PO-104')
        ->set('lines', [[
            'product_id' => $product->getKey(),
            'quantity' => '2',
            'price' => '0.01',
            'total' => '0.02',
            'currency' => 'GBP',
            'sku' => 'tampered',
            'name' => 'tampered',
        ]])
        ->call('submit')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('sales_orders', [
        'account_id' => $user->account_id,
        'total_amount' => 25,
        'origin' => OrderOrigin::PORTAL->value,
        'erp_sync_state' => ErpSyncState::PENDING->value,
        'external_id' => null,
        'customer_reference' => 'PO-104',
    ]);
    $this->assertDatabaseHas('sales_order_lines', [
        'product_id' => $product->getKey(),
        'unit_price' => 12.50,
        'total_price' => 25,
    ]);
});

it('rejects a tampered product identifier', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = orderSubmissionVendor();
    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('lines', [['product_id' => 999999, 'quantity' => '1']])
        ->call('submit')
        ->assertHasErrors(['lines']);

    $this->assertDatabaseCount('sales_orders', 0);
});

it('returns the original order when a submission token is retried', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = orderSubmissionVendor();
    $product = Product::factory()->create(['price' => '10.00', 'currency' => 'GBP']);
    $token = (string) Str::uuid();
    $action = app(SubmitVendorOrder::class);

    $first = $action->handle($user, $token, null, [['product_id' => $product->getKey(), 'quantity' => '1']]);
    $second = $action->handle($user, $token, null, [['product_id' => $product->getKey(), 'quantity' => '99']]);

    expect($second->is($first))->toBeTrue()
        ->and($second->erp_sync_state)->toBe(ErpSyncState::PENDING);
    $this->assertDatabaseCount('sales_orders', 1);
    $this->assertDatabaseCount('sales_order_lines', 1);
});
