<?php

namespace App\Portal\Assistant\Data;

readonly class AiProviderResponse
{
    /** @param list<AiToolCall> $toolCalls */
    public function __construct(
        public string $content,
        public array $toolCalls = [],
    ) {}
}
