<?php

namespace App\Portal\Assistant;

use App\Portal\Assistant\Contracts\AiProvider;
use App\Portal\Assistant\Data\AiProviderResponse;
use App\Portal\Assistant\Exceptions\AiProviderException;
use Closure;
use Laravel\Ai\AiManager;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Throwable;

class LaravelAiProvider implements AiProvider
{
    public function __construct(
        private readonly AiManager $ai,
        private readonly VendorAssistantConfiguration $configuration,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  Closure(string, array<string, mixed>): array<string, mixed>  $executeTool
     */
    public function complete(string $instructions, array $messages, array $tools, Closure $executeTool): AiProviderResponse
    {
        $providerConfiguration = $this->configuration->provider();
        $prompt = array_pop($messages);

        if (! is_array($prompt) || $prompt['role'] !== 'user') {
            throw new AiProviderException('The assistant conversation does not contain a user prompt.');
        }

        $toolResults = [];
        $toolCallCount = 0;
        $maxToolCalls = max(1, min((int) config('vendor_assistant.max_tool_calls'), 30));
        $execute = function (string $name, array $arguments) use ($executeTool, &$toolResults, &$toolCallCount, $maxToolCalls): array {
            $toolCallCount++;

            if ($toolCallCount > $maxToolCalls) {
                throw new AiProviderException('The assistant exceeded the allowed number of tool calls.');
            }

            $payload = $executeTool($name, $arguments);

            if (($payload['ok'] ?? false) === true && is_array($payload['data'] ?? null)) {
                $toolResults[] = ['name' => $name, 'result' => $payload['data']];
            }

            return $payload;
        };
        $agent = new CoreviaVendorAgent(
            $instructions,
            $this->messages($messages),
            array_map(fn (array $tool): RegisteredAssistantTool => new RegisteredAssistantTool($tool, $execute), $tools),
        );

        try {
            $response = $agent->prompt(
                $prompt['content'],
                provider: [$this->ai->build($providerConfiguration)],
                model: $this->configuration->model(),
                timeout: max(1, (int) config('vendor_assistant.timeout')),
            );
        } catch (AiProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new AiProviderException('The assistant provider timed out or returned an error.', previous: $exception);
        }

        if (trim($response->text) === '') {
            throw new AiProviderException('The assistant provider returned an empty response.');
        }

        return new AiProviderResponse($response->text, $toolResults);
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return list<Message>
     */
    private function messages(array $messages): array
    {
        return array_map(
            fn (array $message): Message => $message['role'] === 'assistant'
                ? new AssistantMessage($message['content'])
                : new UserMessage($message['content']),
            $messages,
        );
    }
}
