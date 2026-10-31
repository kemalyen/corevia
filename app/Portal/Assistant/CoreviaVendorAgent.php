<?php

namespace App\Portal\Assistant;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class CoreviaVendorAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  list<Message>  $conversationMessages
     * @param  list<RegisteredAssistantTool>  $registeredTools
     */
    public function __construct(
        private readonly string $systemInstructions,
        private readonly array $conversationMessages,
        private readonly array $registeredTools,
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->systemInstructions;
    }

    /** @return list<Message> */
    public function messages(): iterable
    {
        return $this->conversationMessages;
    }

    /** @return list<RegisteredAssistantTool> */
    public function tools(): iterable
    {
        return $this->registeredTools;
    }

    public function maxSteps(): int
    {
        return max(1, min((int) config('vendor_assistant.max_tool_rounds'), 10));
    }

    public function maxTokens(): int
    {
        return max(1, (int) config('vendor_assistant.max_output_tokens'));
    }
}
