<?php

namespace Tests\Fakes;

use App\Portal\Assistant\Contracts\AiProvider;
use App\Portal\Assistant\Data\AiProviderResponse;
use Closure;

class ConversationFakeProvider implements AiProvider
{
    public function complete(string $instructions, array $messages, array $tools, Closure $executeTool): AiProviderResponse
    {
        return new AiProviderResponse('I found the trusted account information.');
    }
}
