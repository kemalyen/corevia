<?php

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Livewire\Portal\Orders\Show;
use App\Livewire\Portal\Reports;
use App\Models\Account;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('isolates order lists details and downloads by vendor account', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $ownAccount = Account::factory()->create(['account_type' => AccountType::VENDOR, 'account_status' => AccountStatus::ACTIVE]);
    $otherAccount = Account::factory()->create(['account_type' => AccountType::VENDOR, 'account_status' => AccountStatus::ACTIVE]);
    $user = User::factory()->for($ownAccount)->create();
    $user->assignRole('vendor-user');
    $ownOrder = SalesOrder::factory()->for($ownAccount)->create(['order_number' => 'OWN-ORDER', 'currency' => 'GBP']);
    $otherOrder = SalesOrder::factory()->for($otherAccount)->create(['order_number' => 'OTHER-ORDER', 'currency' => 'GBP']);
    $ownProduct = Product::factory()->create(['sku' => 'OWN-SKU']);
    $otherProduct = Product::factory()->create(['sku' => 'OTHER-SKU']);
    $ownOrder->lines()->create(['product_id' => $ownProduct->getKey(), 'quantity' => 1, 'unit_price' => 10, 'total_price' => 10, 'sku' => 'OWN-SKU']);
    $otherOrder->lines()->create(['product_id' => $otherProduct->getKey(), 'quantity' => 1, 'unit_price' => 10, 'total_price' => 10, 'sku' => 'OTHER-SKU']);

    $this->actingAs($user)->get(route('portal.orders.index'))
        ->assertOk()
        ->assertSee('OWN-ORDER')
        ->assertDontSee('OTHER-ORDER');
    $this->get(route('portal.orders.show', $ownOrder))->assertOk();
    $this->get(route('portal.orders.show', $otherOrder))->assertNotFound();
    $download = $this->get(route('portal.reports.orders.csv'))->assertOk();

    expect($download->streamedContent())
        ->toContain('OWN-ORDER')
        ->not->toContain('OTHER-ORDER');

    Livewire::test(Reports::class)
        ->assertSee('OWN-SKU')
        ->assertDontSee('OTHER-SKU');
});

it('rejects tampering with the locked order identifier', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $account = Account::factory()->create(['account_type' => AccountType::VENDOR, 'account_status' => AccountStatus::ACTIVE]);
    $otherAccount = Account::factory()->create(['account_type' => AccountType::VENDOR, 'account_status' => AccountStatus::ACTIVE]);
    $user = User::factory()->for($account)->create();
    $user->assignRole('vendor-user');
    $ownOrder = SalesOrder::factory()->for($account)->create(['currency' => 'GBP']);
    $otherOrder = SalesOrder::factory()->for($otherAccount)->create(['currency' => 'GBP']);
    $this->actingAs($user);

    expect(fn () => Livewire::test(Show::class, ['salesOrder' => $ownOrder])
        ->set('salesOrderId', $otherOrder->getKey()))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('escapes spreadsheet formulas in report downloads', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $account = Account::factory()->create(['account_type' => AccountType::VENDOR, 'account_status' => AccountStatus::ACTIVE]);
    $user = User::factory()->for($account)->create();
    $user->assignRole('vendor-user');
    SalesOrder::factory()->for($account)->create(['order_number' => '=DANGEROUS', 'currency' => 'GBP']);

    $download = $this->actingAs($user)->get(route('portal.reports.orders.csv'))->assertOk();

    expect($download->streamedContent())->toContain("'=DANGEROUS");
});
