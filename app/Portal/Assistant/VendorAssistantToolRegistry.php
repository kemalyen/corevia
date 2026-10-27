<?php

namespace App\Portal\Assistant;

use App\Models\Account;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VendorAssistantToolRegistry
{
    public function __construct(private VendorAssistantOperations $operations) {}

    /** @return list<array<string, mixed>> */
    public function definitions(): array
    {
        return [
            $this->definition('search_products', 'Search products visible to the active vendor account. Use this before choosing a product.', [
                'query' => ['type' => 'string', 'description' => 'At least two characters of a product code or name.'],
            ], ['query']),
            $this->definition('get_product', 'Get a visible product and its effective account price.', $this->productIdProperty(), ['product_id']),
            $this->definition('get_stock', 'Get the available stock status for a visible product.', $this->productIdProperty(), ['product_id']),
            $this->definition('list_orders', 'List recent orders for the active vendor account.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
            ]),
            $this->definition('get_order', 'Get an order belonging to the active vendor account.', [
                'order_id' => ['type' => 'integer'],
            ], ['order_id']),
            $this->definition('get_order_draft', 'Show the current order draft shared with the traditional order form.', []),
            $this->definition('add_order_draft_line', 'Add or update one exact visible product in the draft. Never call this when search is ambiguous.', [
                ...$this->productIdProperty(),
                'quantity' => ['type' => 'string', 'description' => 'A positive decimal quantity, at most four decimal places.'],
            ], ['product_id', 'quantity']),
            $this->definition('update_order_draft_line', 'Update the quantity of one exact product already in the draft.', [
                ...$this->productIdProperty(),
                'quantity' => ['type' => 'string'],
            ], ['product_id', 'quantity']),
            $this->definition('remove_order_draft_line', 'Remove one exact product from the current draft.', $this->productIdProperty(), ['product_id']),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(string $name, array $arguments, User $user, Account $account): array
    {
        $rules = match ($name) {
            'search_products' => ['query' => ['required', 'string', 'min:2', 'max:100']],
            'get_product', 'get_stock', 'remove_order_draft_line' => ['product_id' => ['required', 'integer', 'min:1']],
            'list_orders' => ['limit' => ['sometimes', 'integer', 'between:1,20']],
            'get_order' => ['order_id' => ['required', 'integer', 'min:1']],
            'get_order_draft' => [],
            'add_order_draft_line', 'update_order_draft_line' => [
                'product_id' => ['required', 'integer', 'min:1'],
                'quantity' => ['required', 'numeric', 'gt:0', 'max:999999.9999', 'decimal:0,4'],
            ],
            default => throw new DomainException("Unknown assistant tool [{$name}]."),
        };

        if (array_diff(array_keys($arguments), array_keys($rules)) !== []) {
            throw ValidationException::withMessages(['tool' => 'The tool contained unexpected arguments.']);
        }

        $validated = Validator::make($arguments, $rules)->validate();

        return match ($name) {
            'search_products' => $this->operations->searchProducts($user, $account, $validated['query']),
            'get_product' => $this->operations->product($user, $account, $validated['product_id']),
            'get_stock' => $this->operations->stock($user, $account, $validated['product_id']),
            'list_orders' => $this->operations->listOrders($user, $account, $validated['limit'] ?? 10),
            'get_order' => $this->operations->order($user, $account, $validated['order_id']),
            'get_order_draft' => $this->operations->draft($user, $account),
            'add_order_draft_line', 'update_order_draft_line' => $this->operations->addDraftLine($user, $account, $validated['product_id'], (string) $validated['quantity']),
            'remove_order_draft_line' => $this->operations->removeDraftLine($user, $account, $validated['product_id']),
        };
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    private function definition(string $name, string $description, array $properties, array $required = []): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $required,
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /** @return array<string, array<string, string>> */
    private function productIdProperty(): array
    {
        return ['product_id' => ['type' => 'integer', 'description' => 'The exact server-returned product ID.']];
    }
}
