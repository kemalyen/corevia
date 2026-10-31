<?php

namespace App\Portal\Assistant\Data;

readonly class AiProviderResponse
{
    /** @param list<array{name: string, result: array<string, mixed>}> $toolResults */
    public function __construct(
        public string $content,
        public array $toolResults = [],
    ) {}
}
