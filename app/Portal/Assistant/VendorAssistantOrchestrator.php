<?php

namespace App\Portal\Assistant;

use App\Models\Account;
use App\Models\User;
use App\Portal\Assistant\Contracts\AiProvider;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class VendorAssistantOrchestrator
{
    public function __construct(
        private AiProvider $provider,
        private VendorAssistantToolRegistry $tools,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return array{content: string, tool_results: list<array{name: string, result: array<string, mixed>}>}
     */
    public function respond(User $user, Account $account, array $history): array
    {
        $maxContext = max(2, (int) config('vendor_assistant.max_context_messages'));
        $response = $this->provider->complete(
            $this->systemPrompt($account),
            array_slice($history, -$maxContext),
            $this->tools->definitions(),
            function (string $name, array $arguments) use ($user, $account): array {
                try {
                    $result = $this->tools->execute($name, $arguments, $user, $account);
                    $payload = ['ok' => true, 'data' => $result];
                    $status = 'ok';
                } catch (Throwable $exception) {
                    $payload = ['ok' => false, 'error' => $this->safeToolError($exception)];
                    $status = 'rejected';
                }

                Log::info('Vendor assistant tool completed.', [
                    'user_id' => $user->getKey(),
                    'account_id' => $account->getKey(),
                    'tool' => $name,
                    'status' => $status,
                ]);

                return $payload;
            },
        );

        return ['content' => $response->content, 'tool_results' => $response->toolResults];
    }

    private function systemPrompt(Account $account): string
    {
        return <<<PROMPT
You are the Corevia vendor assistant for the active account {$account->name}.
Use only the supplied tools for products, prices, stock, orders, and the order draft. Never invent IDs, prices, availability, order state, or tool results. Search first and ask the user to disambiguate when more than one product could match. Never mutate a draft from an ambiguous match. Adding and updating draft lines requires an exact product ID and a positive quantity. Stock may be unknown; state that clearly. You cannot submit orders. Tell the user to review the server-refreshed draft and explicitly confirm in the interface. A submitted request remains pending and is not ERP acceptance or a stock reservation. Do not reveal data outside the active account.
PROMPT;
    }

    private function safeToolError(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof ValidationException => 'The tool arguments were invalid.',
            $exception instanceof AuthorizationException,
            $exception instanceof ModelNotFoundException => 'The requested record is not available to this account.',
            $exception instanceof \DomainException => $exception->getMessage(),
            default => 'The operation could not be completed.',
        };
    }
}
