<?php

use App\Portal\Assistant\Exceptions\AiProviderException;
use App\Portal\Assistant\VendorAssistantConfiguration;

beforeEach(function (): void {
    config()->set('vendor_assistant.enabled', true);
    config()->set('vendor_assistant.provider', 'openai-compatible');
    config()->set('vendor_assistant.api_key', null);
    config()->set('vendor_assistant.model', 'msty-tool-model');
    config()->set('vendor_assistant.base_url', 'http://host.docker.internal:10000/v1');
    config()->set('vendor_assistant.allowed_base_urls', 'http://host.docker.internal:10000/v1');
    config()->set('vendor_assistant.tool_calling_enabled', true);
});

it('builds an allowlisted OpenAI-compatible provider configuration', function (): void {
    $provider = app(VendorAssistantConfiguration::class)->provider();

    expect($provider)->toMatchArray([
        'driver' => 'openai-compatible',
        'url' => 'http://host.docker.internal:10000/v1',
        'models' => ['text' => ['default' => 'msty-tool-model']],
    ])->not->toHaveKey('key');
});

it('fails closed for incomplete or unsupported configuration', function (string $key, mixed $value, string $message): void {
    config()->set("vendor_assistant.{$key}", $value);

    expect(fn () => app(VendorAssistantConfiguration::class)->provider())
        ->toThrow(AiProviderException::class, $message);
})->with([
    'disabled' => ['enabled', false, 'not enabled'],
    'missing provider' => ['provider', null, 'provider is not configured'],
    'unsupported provider' => ['provider', 'unsupported', 'provider is not supported'],
    'missing model' => ['model', null, 'model is not configured'],
    'unsupported tools' => ['tool_calling_enabled', false, 'must explicitly support tool calling'],
    'missing compatible URL' => ['base_url', null, 'requires a base URL'],
    'unapproved endpoint' => ['allowed_base_urls', 'https://approved.example/v1', 'not allowlisted'],
]);

it('requires credentials for hosted providers', function (string $provider): void {
    config()->set('vendor_assistant.provider', $provider);
    config()->set('vendor_assistant.api_key', null);
    config()->set('vendor_assistant.base_url', null);

    expect(fn () => app(VendorAssistantConfiguration::class)->provider())
        ->toThrow(AiProviderException::class, 'requires an API key');
})->with(['openai', 'groq']);

it('does not leak credentials or endpoint details in configuration errors', function (): void {
    $secret = 'super-secret-provider-key';
    $privateEndpoint = 'http://user:password@private.internal:10000/v1';
    config()->set('vendor_assistant.api_key', $secret);
    config()->set('vendor_assistant.base_url', $privateEndpoint);

    try {
        app(VendorAssistantConfiguration::class)->provider();
        $this->fail('Expected invalid configuration to be rejected.');
    } catch (AiProviderException $exception) {
        expect($exception->getMessage())
            ->not->toContain($secret)
            ->not->toContain($privateEndpoint)
            ->not->toContain('password');
    }
});
