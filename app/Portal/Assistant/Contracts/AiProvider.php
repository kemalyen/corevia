<?php

namespace App\Portal\Assistant\Contracts;

use App\Portal\Assistant\Data\AiProviderResponse;

interface AiProvider
{
    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     */
    public function complete(array $messages, array $tools): AiProviderResponse;
}
