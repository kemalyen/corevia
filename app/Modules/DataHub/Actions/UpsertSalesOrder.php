<?php

namespace App\Modules\DataHub\Actions;

use App\Models\Account;
use App\Models\Product;
use App\Models\SalesOrder;
use DomainException;
use Illuminate\Support\Facades\DB;

class UpsertSalesOrder
{
    /**
     * @param  array{external_id: string, order_number: string, order_date: string, customer_external_id: string, status: string, currency: string, total_amount: string, lines: array<int, array{external_id: string, line_number: ?string, product_external_id: string, quantity: string, unit_price: string, total_price: string, description: ?string, sku: ?string, unit_of_measure: ?string}>}  $data
     * @return array{created: int, updated: int}
     */
    public function handle(string $source, array $data): array
    {
        $account = Account::query()
            ->where('source', $source)
            ->where('external_id', $data['customer_external_id'])
            ->first();

        if ($account === null) {
            throw new DomainException("Unknown customer [{$data['customer_external_id']}].");
        }

        $productExternalIds = collect($data['lines'])->pluck('product_external_id')->unique()->values();
        $products = Product::query()
            ->where('source', $source)
            ->whereIn('external_id', $productExternalIds)
            ->get()
            ->keyBy('external_id');
        $unknownProduct = $productExternalIds->first(fn (string $externalId): bool => ! $products->has($externalId));

        if ($unknownProduct !== null) {
            throw new DomainException("Unknown product [{$unknownProduct}].");
        }

        return DB::transaction(function () use ($account, $data, $products, $source): array {
            $salesOrder = SalesOrder::query()->firstOrNew([
                'source' => $source,
                'external_id' => $data['external_id'],
            ]);
            $created = $salesOrder->exists ? 0 : 1;
            $updated = $salesOrder->exists ? 1 : 0;

            $salesOrder->fill([
                'order_number' => $data['order_number'],
                'order_date' => $data['order_date'],
                'account_id' => $account->getKey(),
                'total_amount' => $data['total_amount'],
                'status' => $data['status'],
                'currency' => $data['currency'],
            ])->save();

            foreach ($data['lines'] as $lineData) {
                $line = $salesOrder->lines()->firstOrNew([
                    'source' => $source,
                    'external_id' => $lineData['external_id'],
                ]);
                $created += $line->exists ? 0 : 1;
                $updated += $line->exists ? 1 : 0;
                $product = $products->get($lineData['product_external_id']);

                $line->fill([
                    'product_id' => $product->getKey(),
                    'quantity' => $lineData['quantity'],
                    'unit_price' => $lineData['unit_price'],
                    'total_price' => $lineData['total_price'],
                    'description' => $lineData['description'],
                    'sku' => $lineData['sku'],
                    'line_number' => $lineData['line_number'],
                    'unit_of_measure' => $lineData['unit_of_measure'],
                ])->save();
            }

            return ['created' => $created, 'updated' => $updated];
        });
    }
}
