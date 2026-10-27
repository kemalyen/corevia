<?php

namespace App\Portal\Assistant;

use App\Portal\Assistant\Exceptions\AiProviderException;

class VendorAssistantConfiguration
{
    /** @var list<string> */
    private const SUPPORTED_PROVIDERS = ['openai', 'groq', 'openai-compatible'];

    /** @return array<string, mixed> */
    public function provider(): array
    {
        if (! config('vendor_assistant.enabled')) {
            throw new AiProviderException('The vendor assistant is not enabled.');
        }

        if (! config('vendor_assistant.tool_calling_enabled')) {
            throw new AiProviderException('The configured assistant model must explicitly support tool calling.');
        }

        $provider = $this->requiredString('provider');
        $model = $this->requiredString('model');

        if (! in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            throw new AiProviderException('The configured assistant provider is not supported.');
        }

        $apiKey = $this->optionalString('api_key');

        if (in_array($provider, ['openai', 'groq'], true) && $apiKey === null) {
            throw new AiProviderException('The configured assistant provider requires an API key.');
        }

        $baseUrl = $this->optionalString('base_url');

        if ($provider === 'openai-compatible' && $baseUrl === null) {
            throw new AiProviderException('The OpenAI-compatible assistant provider requires a base URL.');
        }

        if ($baseUrl !== null) {
            $baseUrl = $this->validatedBaseUrl($baseUrl);
        }

        return array_filter([
            'name' => 'corevia_vendor_assistant',
            'driver' => $provider,
            'key' => $apiKey,
            'url' => $baseUrl,
            'store' => false,
            'models' => ['text' => ['default' => $model]],
        ], fn (mixed $value): bool => $value !== null);
    }

    public function model(): string
    {
        return $this->requiredString('model');
    }

    private function validatedBaseUrl(string $baseUrl): string
    {
        $normalized = rtrim($baseUrl, '/');
        $parts = parse_url($normalized);

        if (! is_array($parts)
            || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            || ! isset($parts['host'])
            || isset($parts['user'], $parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new AiProviderException('The configured assistant base URL is invalid.');
        }

        $allowedBaseUrls = array_values(array_filter(array_map(
            fn (string $url): string => rtrim(trim($url), '/'),
            explode(',', (string) config('vendor_assistant.allowed_base_urls')),
        )));

        if (! in_array($normalized, $allowedBaseUrls, true)) {
            throw new AiProviderException('The configured assistant base URL is not allowlisted.');
        }

        return $normalized;
    }

    private function requiredString(string $key): string
    {
        $value = $this->optionalString($key);

        if ($value === null) {
            throw new AiProviderException("The vendor assistant {$key} is not configured.");
        }

        return $value;
    }

    private function optionalString(string $key): ?string
    {
        $value = config("vendor_assistant.{$key}");

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
