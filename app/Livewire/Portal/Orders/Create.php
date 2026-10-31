<?php

namespace App\Livewire\Portal\Orders;

use App\Models\Account;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Portal\Actions\AddVendorOrderDraftLine;
use App\Portal\Actions\RefreshVendorOrderDraft;
use App\Portal\Actions\SubmitVendorOrder;
use App\Portal\Orders\VendorOrderDraft;
use App\Portal\Queries\VendorCatalogueQuery;
use App\Portal\VendorAccountContext;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::portal')]
#[Title('Create order')]
class Create extends Component
{
    #[Locked]
    public ?int $selectedProductId = null;

    #[Locked]
    public ?string $selectedProductLabel = null;

    #[Locked]
    public int $activeAccountId;

    public string $quantity = '1';

    public string $customerReference = '';

    /** @var array<int, array<string, mixed>> */
    public array $lines = [];

    #[Locked]
    public string $submissionToken;

    #[Locked]
    public ?int $submittedOrderId = null;

    public function mount(VendorAccountContext $accountContext, VendorOrderDraft $draft): void
    {
        $this->activeAccountId = $accountContext->resolve($this->user())->getKey();
        $this->lines = $draft->current($this->user());
        $this->submissionToken = (string) Str::uuid();
    }

    /**
     * @return array{status: 'ok'|'too_short'|'error', results: list<array{id: int, code: string, name: string, label: string, availability: null, price: array{amount: string, currency: string, unit_of_measure: string, source: string}|null}>, has_more: bool}
     */
    public function searchProducts(
        string $search,
        VendorAccountContext $accountContext,
        VendorCatalogueQuery $catalogue,
    ): array {
        $this->synchroniseAccount($accountContext);

        if (Str::length(trim($search)) < 2) {
            return ['status' => 'too_short', 'results' => [], 'has_more' => false];
        }

        try {
            return ['status' => 'ok', ...$catalogue->search($this->user(), $search)];
        } catch (QueryException $exception) {
            report($exception);

            return ['status' => 'error', 'results' => [], 'has_more' => false];
        }
    }

    /** @return array{id: int, label: string} */
    public function selectProduct(
        int $productId,
        VendorAccountContext $accountContext,
        VendorCatalogueQuery $catalogue,
    ): array {
        $this->synchroniseAccount($accountContext);
        $product = $catalogue->findOrderableForUser($this->user(), $productId);

        if (! $product instanceof Product || $catalogue->effectivePriceForUser($this->user(), $product) === null) {
            throw ValidationException::withMessages([
                'selectedProductId' => 'This product is not available with valid pricing.',
            ]);
        }

        $this->selectedProductId = $product->getKey();
        $this->selectedProductLabel = "{$product->sku} — {$product->name}";

        return ['id' => $product->getKey(), 'label' => $this->selectedProductLabel];
    }

    public function clearProductSelection(): void
    {
        $this->reset('selectedProductId', 'selectedProductLabel');
    }

    public function addLine(
        VendorAccountContext $accountContext,
        AddVendorOrderDraftLine $addDraftLine,
        VendorOrderDraft $draft,
    ): void {
        $this->synchroniseAccount($accountContext);
        $validated = $this->validate([
            'selectedProductId' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999.9999', 'decimal:0,4'],
        ]);
        try {
            $this->lines = $addDraftLine->handle(
                $this->user(),
                (int) $validated['selectedProductId'],
                $validated['quantity'],
                $this->lines,
            );
            $draft->replace($this->user(), $this->lines);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'selectedProductId' => $exception->getMessage(),
            ]);
        }

        $this->clearProductSelection();
        $this->quantity = '1';
        $this->dispatch('product-selection-cleared');
    }

    public function removeLine(int $index, VendorAccountContext $accountContext, VendorOrderDraft $draft): void
    {
        $this->synchroniseAccount($accountContext);
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        $draft->replace($this->user(), $this->lines);
    }

    public function submit(
        VendorAccountContext $accountContext,
        RefreshVendorOrderDraft $refreshDraft,
        SubmitVendorOrder $submitOrder,
        VendorOrderDraft $draft,
    ): void {
        $this->synchroniseAccount($accountContext);

        if ($this->submittedOrderId !== null) {
            return;
        }

        $validated = $this->validate([
            'customerReference' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999.9999', 'decimal:0,4'],
        ]);

        try {
            $refreshedLines = $refreshDraft->handle($this->user(), $validated['lines']);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['lines' => $exception->getMessage()]);
        }

        if ($this->pricingSignature($this->lines) !== $this->pricingSignature($refreshedLines)) {
            $this->lines = $refreshedLines;
            $draft->replace($this->user(), $this->lines);

            throw ValidationException::withMessages([
                'lines' => 'Pricing or product access changed. Review the revised draft and confirm submission again.',
            ]);
        }

        try {
            $order = $submitOrder->handle(
                $this->user(),
                $this->submissionToken,
                $validated['customerReference'] ?? null,
                array_map(fn (array $line): array => [
                    'product_id' => (int) $line['product_id'],
                    'quantity' => (string) $line['quantity'],
                    'price' => (string) $line['price'],
                    'currency' => (string) $line['currency'],
                    'unit_of_measure' => (string) ($line['unit_of_measure'] ?? ''),
                    'price_source' => (string) $line['price_source'],
                ], $refreshedLines),
            );
        } catch (DomainException $exception) {
            if (Str::startsWith($exception->getMessage(), 'Pricing changed')) {
                $this->lines = $refreshDraft->handle($this->user(), $refreshedLines);
            }

            throw ValidationException::withMessages(['lines' => $exception->getMessage()]);
        }

        $this->submittedOrderId = $order->getKey();
        $draft->clear();
    }

    public function render(VendorAccountContext $accountContext): View
    {
        $account = $this->synchroniseAccount($accountContext);
        $submittedOrder = $this->submittedOrderId === null
            ? null
            : SalesOrder::query()->whereKey($this->submittedOrderId)->whereBelongsTo($account)->firstOrFail();

        return view('livewire.portal.orders.create', compact('submittedOrder'));
    }

    private function synchroniseAccount(VendorAccountContext $accountContext): Account
    {
        $account = $accountContext->resolve($this->user());

        if (isset($this->activeAccountId) && $this->activeAccountId !== $account->getKey()) {
            $this->activeAccountId = $account->getKey();
            $this->clearProductSelection();
            $this->lines = [];
            $this->submissionToken = (string) Str::uuid();
            $this->submittedOrderId = null;
            $this->dispatch('product-selection-cleared');
        }

        return $account;
    }

    private function user(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, string|int>>
     */
    private function pricingSignature(array $lines): array
    {
        return array_map(fn (array $line): array => [
            'product_id' => (int) ($line['product_id'] ?? 0),
            'quantity' => (string) ($line['quantity'] ?? ''),
            'price' => (string) ($line['price'] ?? ''),
            'currency' => (string) ($line['currency'] ?? ''),
            'unit_of_measure' => (string) ($line['unit_of_measure'] ?? ''),
            'price_source' => (string) ($line['price_source'] ?? ''),
        ], $lines);
    }
}
