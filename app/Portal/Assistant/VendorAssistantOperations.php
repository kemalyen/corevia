<?php

namespace App\Portal\Assistant;

use App\Models\Account;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Portal\Orders\VendorOrderDraft;
use App\Portal\Queries\VendorCatalogueQuery;
use App\Portal\Queries\VendorOrderQuery;
use App\Portal\VendorAccountContext;
use Illuminate\Auth\Access\AuthorizationException;

class VendorAssistantOperations
{
    public function __construct(
        private VendorAccountContext $accountContext,
        private VendorCatalogueQuery $catalogue,
        private VendorOrderQuery $orders,
        private VendorOrderDraft $draft,
    ) {}

    /** @return array<string, mixed> */
    public function searchProducts(User $user, Account $account, string $query): array
    {
        $this->assertAccount($user, $account);

        return $this->catalogue->search($user, $query, 10);
    }

    /** @return array<string, mixed> */
    public function product(User $user, Account $account, int $productId): array
    {
        $this->assertAccount($user, $account);
        $product = $this->catalogue->findOrderableForUser($user, $productId);

        if (! $product instanceof Product) {
            throw new AuthorizationException('The product is not available to this account.');
        }

        return [
            'id' => $product->getKey(),
            'code' => $product->sku,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $this->catalogue->effectivePriceForUser($user, $product)?->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function stock(User $user, Account $account, int $productId): array
    {
        $product = $this->product($user, $account, $productId);

        return [
            'product' => $product,
            'status' => 'unknown',
            'quantity' => null,
            'source_timestamp' => null,
            'message' => 'No vendor-safe stock feed is configured. Stock has not been reserved.',
        ];
    }

    /** @return array<string, mixed> */
    public function listOrders(User $user, Account $account, int $limit): array
    {
        $this->assertAccount($user, $account);
        $orders = $this->orders->forUser($user)
            ->latest('order_date')
            ->limit(min(max($limit, 1), 20))
            ->get();

        return ['orders' => $orders->map(fn (SalesOrder $order): array => $this->orderSummary($order))->all()];
    }

    /** @return array<string, mixed> */
    public function order(User $user, Account $account, int $orderId): array
    {
        $this->assertAccount($user, $account);
        $order = $this->orders->forUser($user)->with('lines')->findOrFail($orderId);

        return [
            ...$this->orderSummary($order),
            'customer_reference' => $order->customer_reference,
            'lines' => $order->lines->map(fn ($line): array => [
                'product_id' => $line->product_id,
                'code' => $line->sku,
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'total' => (string) $line->total_price,
                'currency' => $line->currency,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function draft(User $user, Account $account): array
    {
        $this->assertAccount($user, $account);

        return ['lines' => $this->draft->current($user)];
    }

    /** @return array<string, mixed> */
    public function addDraftLine(User $user, Account $account, int $productId, string $quantity): array
    {
        $this->assertAccount($user, $account);

        return ['lines' => $this->draft->addOrUpdate($user, $productId, $quantity)];
    }

    /** @return array<string, mixed> */
    public function removeDraftLine(User $user, Account $account, int $productId): array
    {
        $this->assertAccount($user, $account);

        return ['lines' => $this->draft->remove($user, $productId)];
    }

    private function assertAccount(User $user, Account $account): void
    {
        if (! $this->accountContext->resolve($user)->is($account)) {
            throw new AuthorizationException('The active vendor account changed.');
        }
    }

    /** @return array<string, mixed> */
    private function orderSummary(SalesOrder $order): array
    {
        return [
            'id' => $order->getKey(),
            'order_number' => $order->order_number,
            'order_date' => $order->order_date->toDateString(),
            'status' => $order->status->value,
            'erp_sync_state' => $order->erp_sync_state->value,
            'total' => (string) $order->total_amount,
            'currency' => $order->currency,
        ];
    }
}
