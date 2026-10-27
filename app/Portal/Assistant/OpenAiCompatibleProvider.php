<?php

namespace App\Portal\Assistant;

use App\Portal\Assistant\Contracts\AiProvider;
use App\Portal\Assistant\Data\AiProviderResponse;
use App\Portal\Assistant\Data\AiToolCall;
use App\Portal\Assistant\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;
use Throwable;

class OpenAiCompatibleProvider implements AiProvider
{
    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     */
    public function complete(array $messages, array $tools): AiProviderResponse
    {
        if (! config('vendor_assistant.enabled')) {
            throw new AiProviderException('The vendor assistant is not enabled.');
        }

        if (! config('vendor_assistant.tool_calling_enabled')) {
            throw new AiProviderException('The configured assistant model must support tool calling.');
        }

        $model = config('vendor_assistant.model');

        if (! is_string($model) || trim($model) === '') {
            throw new AiProviderException('The vendor assistant model is not configured.');
        }

        $request = Http::baseUrl(rtrim((string) config('vendor_assistant.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(max(1, (int) config('vendor_assistant.connect_timeout')))
            ->timeout(max(1, (int) config('vendor_assistant.timeout')));
        $apiKey = config('vendor_assistant.api_key');

        if (is_string($apiKey) && $apiKey !== '') {
            $request = $request->withToken($apiKey);
        }

        try {
            $response = $request->post('/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
            ])->throw();
        } catch (ConnectionException $exception) {
            throw new AiProviderException('The assistant provider timed out or could not be reached.', previous: $exception);
        } catch (Throwable $exception) {
            throw new AiProviderException('The assistant provider returned an error.', previous: $exception);
        }

        $message = $response->json('choices.0.message');

        if (! is_array($message)) {
            throw new AiProviderException('The assistant provider returned an invalid response.');
        }

        $toolCalls = [];

        foreach (($message['tool_calls'] ?? []) as $toolCall) {
            if (! is_array($toolCall) || ! is_array($toolCall['function'] ?? null)) {
                throw new AiProviderException('The assistant provider returned an invalid tool call.');
            }

            try {
                $arguments = json_decode((string) ($toolCall['function']['arguments'] ?? '{}'), true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new AiProviderException('The assistant provider returned invalid tool arguments.', previous: $exception);
            }

            if (! is_array($arguments)) {
                throw new AiProviderException('The assistant provider returned invalid tool arguments.');
            }

            $id = $toolCall['id'] ?? null;
            $name = $toolCall['function']['name'] ?? null;

            if (! is_string($id) || $id === '' || ! is_string($name) || $name === '') {
                throw new AiProviderException('The assistant provider returned an invalid tool call.');
            }

            $toolCalls[] = new AiToolCall(
                id: $id,
                name: $name,
                arguments: $arguments,
            );
        }

        return new AiProviderResponse(
            content: is_string($message['content'] ?? null) ? $message['content'] : '',
            toolCalls: $toolCalls,
        );
    }
}
