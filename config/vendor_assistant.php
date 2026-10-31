<?php

return [
    'enabled' => (bool) env('VENDOR_ASSISTANT_ENABLED', false),
    'provider' => env('VENDOR_ASSISTANT_PROVIDER'),
    'base_url' => env('VENDOR_ASSISTANT_BASE_URL'),
    'allowed_base_urls' => env('VENDOR_ASSISTANT_ALLOWED_BASE_URLS', ''),
    'api_key' => env('VENDOR_ASSISTANT_API_KEY'),
    'model' => env('VENDOR_ASSISTANT_MODEL'),
    'tool_calling_enabled' => (bool) env('VENDOR_ASSISTANT_TOOL_CALLING_ENABLED', false),
    'timeout' => (int) env('VENDOR_ASSISTANT_TIMEOUT', 30),
    'max_output_tokens' => (int) env('VENDOR_ASSISTANT_MAX_OUTPUT_TOKENS', 2048),
    'max_message_characters' => (int) env('VENDOR_ASSISTANT_MAX_MESSAGE_CHARACTERS', 4000),
    'max_context_messages' => (int) env('VENDOR_ASSISTANT_MAX_CONTEXT_MESSAGES', 20),
    'max_tool_rounds' => (int) env('VENDOR_ASSISTANT_MAX_TOOL_ROUNDS', 5),
    'max_tool_calls' => (int) env('VENDOR_ASSISTANT_MAX_TOOL_CALLS', 12),
];
