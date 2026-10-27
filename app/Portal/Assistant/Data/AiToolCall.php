<?php

namespace App\Portal\Assistant\Data;

readonly class AiToolCall
{
    /** @param array<string, mixed> $arguments */
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
    ) {}

    /** @return array<string, mixed> */
    public function toProviderArray(): array
    {
        return [
            'id' => $this->id,
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'arguments' => json_encode($this->arguments, JSON_THROW_ON_ERROR),
            ],
        ];
    }
}
