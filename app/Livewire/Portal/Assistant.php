<?php

namespace App\Livewire\Portal;

use App\Models\Account;
use App\Models\AssistantConversation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Portal\Actions\RefreshVendorOrderDraft;
use App\Portal\Actions\SubmitVendorOrder;
use App\Portal\Assistant\Exceptions\AiProviderException;
use App\Portal\Assistant\VendorAssistantOrchestrator;
use App\Portal\Orders\VendorOrderDraft;
use App\Portal\VendorAccountContext;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::portal')]
#[Title('AI assistant')]
class Assistant extends Component
{
    #[Locked]
    public int $activeAccountId;

    #[Locked]
    public ?int $conversationId = null;

    #[Locked]
    public ?string $reviewedDraftSignature = null;

    #[Locked]
    public ?string $submissionToken = null;

    #[Locked]
    public ?int $submittedOrderId = null;

    public string $message = '';

    public string $customerReference = '';

    public ?string $assistantError = null;

    public function mount(VendorAccountContext $accountContext): void
    {
        $account = $accountContext->resolve($this->user());
        $this->activeAccountId = $account->getKey();
        $storedId = session()->get('portal_assistant_conversation_id');

        if (is_numeric($storedId) && $this->scopedConversations($account)->whereKey((int) $storedId)->exists()) {
            $this->conversationId = (int) $storedId;
        }
    }

    public function newConversation(VendorAccountContext $accountContext): void
    {
        $this->synchroniseAccount($accountContext);
        $this->conversationId = null;
        $this->assistantError = null;
        session()->forget('portal_assistant_conversation_id');
    }

    public function selectConversation(int $conversationId, VendorAccountContext $accountContext): void
    {
        $account = $this->synchroniseAccount($accountContext);
        $conversation = $this->scopedConversations($account)->findOrFail($conversationId);
        $this->conversationId = $conversation->getKey();
        $this->assistantError = null;
        session()->put('portal_assistant_conversation_id', $conversation->getKey());
    }

    public function send(VendorAccountContext $accountContext, VendorAssistantOrchestrator $orchestrator): void
    {
        $account = $this->synchroniseAccount($accountContext);
        $validated = $this->validate([
            'message' => ['required', 'string', 'max:'.max(1, (int) config('vendor_assistant.max_message_characters'))],
        ]);
        $conversation = $this->conversation($account, create: true);
        $content = trim($validated['message']);
        $conversation->messages()->create(['role' => 'user', 'content' => $content]);
        $conversation->update([
            'title' => $conversation->title ?? Str::limit($content, 60),
            'last_message_at' => now(),
        ]);
        $this->message = '';
        $this->assistantError = null;
        $history = array_values($conversation->messages()
            ->latest('id')
            ->limit(max(2, (int) config('vendor_assistant.max_context_messages')))
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn ($message): array => ['role' => $message->role, 'content' => $message->content])
            ->values()
            ->all());

        try {
            $reply = $orchestrator->respond($this->user(), $account, $history);
        } catch (AiProviderException $exception) {
            Log::warning('Vendor assistant provider failed.', [
                'user_id' => $this->user()->getKey(),
                'account_id' => $account->getKey(),
                'conversation_id' => $conversation->getKey(),
                'exception' => $exception::class,
            ]);
            $this->assistantError = $exception->getMessage();

            return;
        }

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $reply['content'],
            'metadata' => ['tool_results' => $reply['tool_results']],
        ]);
        $conversation->update(['last_message_at' => now()]);
    }

    public function reviewDraft(VendorAccountContext $accountContext, VendorOrderDraft $draft, RefreshVendorOrderDraft $refreshDraft): void
    {
        $this->synchroniseAccount($accountContext);

        try {
            $lines = $refreshDraft->handle($this->user(), $draft->current($this->user()));
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['draft' => $exception->getMessage()]);
        }

        $draft->replace($this->user(), $lines);
        $this->reviewedDraftSignature = $this->draftSignature($lines);
        $this->submissionToken = (string) Str::uuid();
        $this->submittedOrderId = null;
    }

    public function confirmDraft(VendorAccountContext $accountContext, VendorOrderDraft $draft, RefreshVendorOrderDraft $refreshDraft, SubmitVendorOrder $submitOrder): void
    {
        $this->synchroniseAccount($accountContext);

        if ($this->submittedOrderId !== null) {
            return;
        }

        if ($this->reviewedDraftSignature === null || $this->submissionToken === null) {
            throw ValidationException::withMessages(['draft' => 'Review the current draft before confirming submission.']);
        }

        $this->validate(['customerReference' => ['nullable', 'string', 'max:255']]);

        try {
            $lines = $refreshDraft->handle($this->user(), $draft->current($this->user()));
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['draft' => $exception->getMessage()]);
        }

        if (! hash_equals($this->reviewedDraftSignature, $this->draftSignature($lines))) {
            $draft->replace($this->user(), $lines);
            $this->reviewedDraftSignature = null;
            $this->submissionToken = null;

            throw ValidationException::withMessages(['draft' => 'Pricing or product access changed. Review the revised draft before confirming.']);
        }

        try {
            $order = $submitOrder->handle($this->user(), $this->submissionToken, $this->customerReference, $lines);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['draft' => $exception->getMessage()]);
        }

        $this->submittedOrderId = $order->getKey();
        $draft->clear();
    }

    public function render(VendorAccountContext $accountContext, VendorOrderDraft $draft): View
    {
        $account = $this->synchroniseAccount($accountContext);
        $conversations = $this->scopedConversations($account)->latest('last_message_at')->limit(50)->get();
        $conversation = $this->conversation($account);
        $messages = $conversation?->messages()->oldest('id')->get() ?? new Collection;
        $draftLines = $draft->current($this->user());
        $submittedOrder = $this->submittedOrderId === null
            ? null
            : SalesOrder::query()->whereKey($this->submittedOrderId)->whereBelongsTo($account)->firstOrFail();

        return view('livewire.portal.assistant', compact('account', 'conversations', 'messages', 'draftLines', 'submittedOrder'));
    }

    private function synchroniseAccount(VendorAccountContext $accountContext): Account
    {
        $account = $accountContext->resolve($this->user());

        if (isset($this->activeAccountId) && $this->activeAccountId !== $account->getKey()) {
            $this->activeAccountId = $account->getKey();
            $this->conversationId = null;
            $this->reviewedDraftSignature = null;
            $this->submissionToken = null;
            $this->submittedOrderId = null;
            $this->assistantError = null;
            session()->forget('portal_assistant_conversation_id');
        }

        return $account;
    }

    private function conversation(Account $account, bool $create = false): ?AssistantConversation
    {
        if ($this->conversationId !== null) {
            return $this->scopedConversations($account)->findOrFail($this->conversationId);
        }

        if (! $create) {
            return null;
        }

        $conversation = AssistantConversation::query()->create([
            'user_id' => $this->user()->getKey(),
            'account_id' => $account->getKey(),
            'last_message_at' => now(),
        ]);
        $this->conversationId = $conversation->getKey();
        session()->put('portal_assistant_conversation_id', $conversation->getKey());

        return $conversation;
    }

    /** @return Builder<AssistantConversation> */
    private function scopedConversations(Account $account): Builder
    {
        return AssistantConversation::query()
            ->whereBelongsTo($this->user())
            ->whereBelongsTo($account);
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function draftSignature(array $lines): string
    {
        return hash('sha256', json_encode(array_map(fn (array $line): array => [
            'product_id' => (int) $line['product_id'],
            'quantity' => (string) $line['quantity'],
            'price' => (string) $line['price'],
            'currency' => (string) $line['currency'],
            'unit_of_measure' => (string) ($line['unit_of_measure'] ?? ''),
            'price_source' => (string) $line['price_source'],
        ], $lines), JSON_THROW_ON_ERROR));
    }

    private function user(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
