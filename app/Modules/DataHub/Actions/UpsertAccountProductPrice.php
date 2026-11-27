<?php

namespace App\Modules\DataHub\Actions;

use App\Models\Account;
use App\Models\AccountProductPrice;
use App\Models\Product;
use DomainException;

class UpsertAccountProductPrice
{
    /**
     * @param  array{account_external_id: string, product_external_id: string, amount: string, currency: string, unit_of_measure: string}  $data
     */
    public function handle(array $data): bool
    {
        $account = Account::query()->where('external_id', $data['account_external_id'])->first();

        if ($account === null) {
            throw new DomainException("Unknown account [{$data['account_external_id']}].");
        }

        if (! $account->isVendor()) {
            throw new DomainException("Account [{$data['account_external_id']}] must be a vendor to have product prices.");
        }

        $product = Product::query()->where('external_id', $data['product_external_id'])->first();

        if ($product === null) {
            throw new DomainException("Unknown product [{$data['product_external_id']}].");
        }

        $price = AccountProductPrice::query()->firstOrNew([
            'account_id' => $account->getKey(),
            'product_id' => $product->getKey(),
            'currency' => $data['currency'],
            'unit_of_measure' => $data['unit_of_measure'],
        ]);
        $wasRecentlyCreated = ! $price->exists;
        $price->amount = $data['amount'];
        $price->save();

        return $wasRecentlyCreated;
    }
}
