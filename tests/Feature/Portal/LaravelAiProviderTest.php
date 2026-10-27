<?php

use App\Portal\Assistant\CoreviaVendorAgent;
use App\Portal\Assistant\Exceptions\AiProviderException;
use App\Portal\Assistant\LaravelAiProvider;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function (): void {
    config()->set('vendor_assistant.enabled', true);
    config()->set('vendor_assistant.provider', 'openai');
    config()->set('vendor_assistant.api_key', 'test-secret-key');
    config()->set('vendor_assistant.model', 'test-tool-model');
    config()->set('vendor_assistant.base_url', null);
    config()->set('vendor_assistant.allowed_base_urls', '');
    config()->set('vendor_assistant.tool_calling_enabled', true);
});

it('uses the configured provider and model while replaying existing history', function (): void {
    CoreviaVendorAgent::fake(['A provider-neutral answer.'])->preventStrayPrompts();

    $response = app(LaravelAiProvider::class)->complete(
        'Trusted instructions.',
        [
            ['role' => 'user', 'content' => 'Earlier question'],
            ['role' => 'assistant', 'content' => 'Earlier answer'],
            ['role' => 'user', 'content' => 'Current question'],
        ],
        [],
        fn (): array => [],
    );

    expect($response->content)->toBe('A provider-neutral answer.');
    CoreviaVendorAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        $messages = [...$prompt->agent->messages()];

        return $prompt->prompt === 'Current question'
            && $prompt->model === 'test-tool-model'
            && $prompt->provider()->name() === 'corevia_vendor_assistant'
            && (string) $prompt->agent->instructions() === 'Trusted instructions.'
            && count($messages) === 2
            && $messages[0]->role->value === 'user'
            && $messages[0]->content === 'Earlier question'
            && $messages[1]->role->value === 'assistant'
            && $messages[1]->content === 'Earlier answer';
    });
});

it('executes only registered tools and records successful results', function (): void {
    CoreviaVendorAgent::fake([
        new ToolCall('call-1', 'get_product', ['product_id' => 42]),
        'Product 42 is available.',
    ])->preventStrayPrompts();
    $executions = [];

    $response = app(LaravelAiProvider::class)->complete(
        'Trusted instructions.',
        [['role' => 'user', 'content' => 'Show product 42']],
        [[
            'type' => 'function',
            'function' => [
                'name' => 'get_product',
                'description' => 'Get one product.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['product_id' => ['type' => 'integer']],
                    'required' => ['product_id'],
                    'additionalProperties' => false,
                ],
            ],
        ]],
        function (string $name, array $arguments) use (&$executions): array {
            $executions[] = compact('name', 'arguments');

            return ['ok' => true, 'data' => ['id' => $arguments['product_id']]];
        },
    );

    expect($executions)->toBe([['name' => 'get_product', 'arguments' => ['product_id' => 42]]])
        ->and($response->toolResults)->toBe([['name' => 'get_product', 'result' => ['id' => 42]]]);
});

it('returns a safe error for provider failures and timeouts', function (string $privateMessage): void {
    CoreviaVendorAgent::fake(fn () => throw new RuntimeException($privateMessage))->preventStrayPrompts();

    expect(fn () => app(LaravelAiProvider::class)->complete(
        'Trusted instructions.',
        [['role' => 'user', 'content' => 'Hello']],
        [],
        fn (): array => [],
    ))->toThrow(AiProviderException::class, 'timed out or returned an error');
})->with([
    'provider error' => 'upstream body containing test-secret-key',
    'timeout' => 'connection timed out at a private endpoint',
]);

it('rejects empty provider responses', function (): void {
    CoreviaVendorAgent::fake([''])->preventStrayPrompts();

    expect(fn () => app(LaravelAiProvider::class)->complete(
        'Trusted instructions.',
        [['role' => 'user', 'content' => 'Hello']],
        [],
        fn (): array => [],
    ))->toThrow(AiProviderException::class, 'returned an empty response');
});
