<?php

namespace App\Portal\Assistant\Contracts;

use App\Portal\Assistant\Data\AiProviderResponse;
use Closure;

interface AiProvider
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  Closure(string, array<string, mixed>): array<string, mixed>  $executeTool
     */
    public function complete(string $instructions, array $messages, array $tools, Closure $executeTool): AiProviderResponse;
}
