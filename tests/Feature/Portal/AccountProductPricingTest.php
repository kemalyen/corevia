<?php

use App\Enums\AccountProductAccessMode;
use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\PriceSource;
use App\Livewire\Portal\Orders\Create;
use App\Models\Account;
use App\Models\AccountProductPrice;
use App\Models\Product;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Modules\DataHub\Actions\UpsertProduct;
use App\Portal\Pricing\ResolveAccountProductPrice;
use Database\Seeders\RolesAndPermissionsSeeder;
use DomainException;
use Livewire\Livewire;

function pricingVendor(): User
{
    $account = Account::factory()->create([
        'account_type' => AccountType::VENDOR,
        'account_status' => AccountStatus::ACTIVE,
        'product_access_mode' => AccountProductAccessMode::ALL,
    ]);
    $user = User::factory()->for($account)->create();
    $user->assignRole('vendor-user');

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('prefers an exact account price and otherwise falls back to the matching feed price', function (): void {
    $account = Account::factory()->create();
    $product = Product::factory()->create([
        'price' => '12.5000',
        'currency' => 'GBP',
        'unit_of_measure' => 'EA',
    ]);
    AccountProductPrice::factory()->for($account)->for($product)->create([
        'amount' => '9.2500',
        'currency' => 'GBP',
        'unit_of_measure' => 'EA',
    ]);
    $resolver = app(ResolveAccountProductPrice::class);

    $accountPrice = $resolver->resolve($account, $product);
    $feedPrice = $resolver->resolve($account, $product, 'GBP', 'BOX');

    expect($accountPrice?->amount)->toBe('9.2500')
        ->and($accountPrice?->source)->toBe(PriceSource::ACCOUNT_SPECIFIC)
        ->and($feedPrice)->toBeNull();

    AccountProductPrice::query()->delete();
    $feedPrice = $resolver->resolve($account, $product);

    expect($feedPrice?->amount)->toBe('12.5000')
        ->and($feedPrice?->currency)->toBe('GBP')
        ->and($feedPrice?->unitOfMeasure)->toBe('EA')
        ->and($feedPrice?->source)->toBe(PriceSource::PRODUCT_FEED);
});

it('treats zero as a valid account price without falling back', function (): void {
    $account = Account::factory()->create();
    $product = Product::factory()->create(['price' => '15.0000', 'currency' => 'GBP']);
    AccountProductPrice::factory()->for($account)->for($product)->create([
        'amount' => '0.0000',
        'currency' => 'GBP',
        'unit_of_measure' => '',
    ]);

    $price = app(ResolveAccountProductPrice::class)->resolve($account, $product);

    expect($price?->amount)->toBe('0.0000')
        ->and($price?->source)->toBe(PriceSource::ACCOUNT_SPECIFIC);
});

it('does not match prices across currency or unit dimensions', function (): void {
    $account = Account::factory()->create();
    $product = Product::factory()->create([
        'price' => '10.0000',
        'currency' => 'GBP',
        'unit_of_measure' => 'EA',
    ]);
    AccountProductPrice::factory()->for($account)->for($product)->create([
        'amount' => '8.0000',
        'currency' => 'USD',
        'unit_of_measure' => 'BOX',
    ]);
    $resolver = app(ResolveAccountProductPrice::class);

    expect($resolver->resolve($account, $product)?->amount)->toBe('10.0000')
        ->and($resolver->resolve($account, $product, 'USD', 'EA'))->toBeNull()
        ->and($resolver->resolve($account, $product, 'GBP', 'BOX'))->toBeNull()
        ->and($resolver->resolve($account, $product, 'USD', 'BOX')?->amount)->toBe('8.0000');
});

it('returns unavailable when no matching price exists and rejects negative legacy prices', function (): void {
    $account = Account::factory()->create();
    $missing = Product::factory()->create(['price' => null, 'currency' => 'GBP']);
    $negative = Product::factory()->create(['price' => '-1.0000', 'currency' => 'GBP']);
    $resolver = app(ResolveAccountProductPrice::class);

    expect($resolver->resolve($account, $missing))->toBeNull()
        ->and(fn () => $resolver->resolve($account, $negative))->toThrow(DomainException::class);
});

it('preserves account overrides when the normal product feed updates', function (): void {
    $account = Account::factory()->create();
    $product = Product::factory()->create([
        'external_id' => '0000123',
        'sku' => 'LEADING-001',
        'price' => '10.0000',
        'currency' => 'GBP',
    ]);
    $override = AccountProductPrice::factory()->for($account)->for($product)->create([
        'amount' => '7.5000',
        'currency' => 'GBP',
        'unit_of_measure' => '',
    ]);

    app(UpsertProduct::class)->handle([
        'external_id' => '0000123',
        'sku' => 'LEADING-001',
        'name' => 'Updated feed product',
        'description' => null,
        'price' => '14.0000',
        'currency' => 'GBP',
    ]);

    expect($override->fresh()->amount)->toBe('7.5000')
        ->and(app(ResolveAccountProductPrice::class)->resolve($account, $product->fresh())?->amount)->toBe('7.5000');
});

it('refreshes changed pricing before submission and preserves the accepted historical price', function (): void {
    $user = pricingVendor();
    $product = Product::factory()->create([
        'sku' => 'REPRICE-1',
        'price' => '10.0000',
        'currency' => 'GBP',
        'unit_of_measure' => 'EA',
    ]);
    $this->actingAs($user);
    $component = Livewire::test(Create::class)
        ->call('selectProduct', $product->getKey())
        ->set('quantity', '2')
        ->call('addLine')
        ->assertSet('lines.0.price', '10.0000');
    $product->update(['price' => '12.0000']);

    $component
        ->call('submit')
        ->assertHasErrors(['lines'])
        ->assertSet('lines.0.price', '12.0000')
        ->assertSet('lines.0.total', '24.00');
    $this->assertDatabaseCount('sales_orders', 0);

    $component->call('submit')->assertHasNoErrors();
    $this->assertDatabaseHas('sales_order_lines', [
        'product_id' => $product->getKey(),
        'unit_price' => 12,
        'currency' => 'GBP',
        'unit_of_measure' => 'EA',
        'price_source' => PriceSource::PRODUCT_FEED->value,
    ]);

    $product->update(['price' => '20.0000']);

    expect(SalesOrderLine::query()->whereBelongsTo($product)->exists())->toBeTrue();
    $this->assertDatabaseHas('sales_order_lines', [
        'product_id' => $product->getKey(),
        'unit_price' => 12,
        'total_price' => 24,
    ]);
});
