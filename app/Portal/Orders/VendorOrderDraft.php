<?php

namespace App\Portal\Orders;

use App\Models\User;
use App\Portal\Actions\AddVendorOrderDraftLine;
use App\Portal\VendorAccountContext;
use DomainException;

class VendorOrderDraft
{
    private const SESSION_KEY = 'portal_order_draft';

    public function __construct(
        private VendorAccountContext $accountContext,
        private AddVendorOrderDraftLine $addDraftLine,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function current(User $user): array
    {
        $account = $this->accountContext->resolve($user);
        $draft = session()->get(self::SESSION_KEY);

        if (! is_array($draft)
            || (int) ($draft['user_id'] ?? 0) !== $user->getKey()
            || (int) ($draft['account_id'] ?? 0) !== $account->getKey()
            || ! is_array($draft['lines'] ?? null)) {
            return [];
        }

        return array_values($draft['lines']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public function replace(User $user, array $lines): array
    {
        $account = $this->accountContext->resolve($user);
        session()->put(self::SESSION_KEY, [
            'user_id' => $user->getKey(),
            'account_id' => $account->getKey(),
            'lines' => array_values($lines),
        ]);

        return array_values($lines);
    }

    /** @return array<int, array<string, mixed>> */
    public function addOrUpdate(User $user, int $productId, string|int|float $quantity): array
    {
        return $this->replace($user, $this->addDraftLine->handle(
            $user,
            $productId,
            $quantity,
            $this->current($user),
        ));
    }

    /** @return array<int, array<string, mixed>> */
    public function remove(User $user, int $productId): array
    {
        $lines = collect($this->current($user))
            ->reject(fn (array $line): bool => (int) ($line['product_id'] ?? 0) === $productId)
            ->values()
            ->all();

        if (count($lines) === count($this->current($user))) {
            throw new DomainException('That product is not in the current draft.');
        }

        return $this->replace($user, $lines);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
