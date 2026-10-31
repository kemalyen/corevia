<?php

use App\Enums\Currency;
use App\Models\AccountProductPrice;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Modules\IntegrationEngine\Models\StagedSalesOrderRow;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

it('provides the supported twenty currencies with GBP first', function (): void {
    $currencies = Currency::cases();

    expect($currencies)
        ->toHaveCount(20)
        ->and($currencies[0])->toBe(Currency::GBP)
        ->and(Currency::USD->getLabel())->toBe('USD — US Dollar');
});

it('defaults every currency model field to GBP', function (): void {
    expect((new Product)->currency)->toBe(Currency::GBP->value)
        ->and((new SalesOrder)->currency)->toBe(Currency::GBP->value)
        ->and((new SalesOrderLine)->currency)->toBe(Currency::GBP->value)
        ->and((new AccountProductPrice)->currency)->toBe(Currency::GBP->value)
        ->and((new StagedSalesOrderRow)->currency)->toBe(Currency::GBP->value);
});

it('rejects currency codes outside the supported enum', function (): void {
    $validator = Validator::make(
        ['currency' => 'XXX'],
        ['currency' => ['required', Rule::enum(Currency::class)]],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('currency'))->toBeTrue();
});
