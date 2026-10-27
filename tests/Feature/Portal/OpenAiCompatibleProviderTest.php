<?php

use App\Portal\Assistant\Exceptions\AiProviderException;
use App\Portal\Assistant\OpenAiCompatibleProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('parses OpenAI-compatible tool calls without sending an empty bearer token', function (): void {
    config()->set('vendor_assistant.enabled', true);
    config()->set('vendor_assistant.base_url', 'http://localhost:10000/v1');
    config()->set('vendor_assistant.model', 'local-model');
    config()->set('vendor_assistant.api_key', null);
    Http::preventStrayRequests();
    Http::fake([
        'localhost:10000/*' => Http::response([
            'choices' => [['message' => [
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call_1',
                    'type' => 'function',
                    'function' => ['name' => 'get_product', 'arguments' => '{"product_id":42}'],
                ]],
            ]]],
        ]),
    ]);

    $response = app(OpenAiCompatibleProvider::class)->complete(
        [['role' => 'user', 'content' => 'Show product 42']],
        [['type' => 'function', 'function' => ['name' => 'get_product']]],
    );

    expect($response->toolCalls)->toHaveCount(1)
        ->and($response->toolCalls[0]->name)->toBe('get_product')
        ->and($response->toolCalls[0]->arguments)->toBe(['product_id' => 42]);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://localhost:10000/v1/chat/completions'
        && ! $request->hasHeader('Authorization'));
});

it('returns a safe provider error for connection failures', function (): void {
    config()->set('vendor_assistant.enabled', true);
    config()->set('vendor_assistant.base_url', 'http://localhost:10000/v1');
    config()->set('vendor_assistant.model', 'local-model');
    Http::preventStrayRequests();
    Http::fake(['localhost:10000/*' => Http::failedConnection()]);

    expect(fn () => app(OpenAiCompatibleProvider::class)->complete(
        [['role' => 'user', 'content' => 'Hello']],
        [],
    ))->toThrow(AiProviderException::class, 'timed out or could not be reached');
});

it('returns a safe provider error for unsuccessful responses', function (): void {
    config()->set('vendor_assistant.enabled', true);
    config()->set('vendor_assistant.base_url', 'http://localhost:10000/v1');
    config()->set('vendor_assistant.model', 'local-model');
    Http::preventStrayRequests();
    Http::fake(['localhost:10000/*' => Http::response(['internal' => 'private detail'], 500)]);

    expect(fn () => app(OpenAiCompatibleProvider::class)->complete(
        [['role' => 'user', 'content' => 'Hello']],
        [],
    ))->toThrow(AiProviderException::class, 'returned an error');
});

it('clearly rejects models without configured tool-calling support', function (): void {
    config()->set('vendor_assistant.enabled', true);
    config()->set('vendor_assistant.tool_calling_enabled', false);

    expect(fn () => app(OpenAiCompatibleProvider::class)->complete([], []))
        ->toThrow(AiProviderException::class, 'must support tool calling');
});
