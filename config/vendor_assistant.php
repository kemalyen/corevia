<?php

return [
    'enabled' => (bool) env('VENDOR_ASSISTANT_ENABLED', false),
    'base_url' => env('VENDOR_ASSISTANT_BASE_URL', 'http://localhost:10000/v1'),
    'api_key' => env('VENDOR_ASSISTANT_API_KEY'),
    'model' => env('VENDOR_ASSISTANT_MODEL'),
    'tool_calling_enabled' => (bool) env('VENDOR_ASSISTANT_TOOL_CALLING_ENABLED', true),
    'timeout' => (int) env('VENDOR_ASSISTANT_TIMEOUT', 30),
    'connect_timeout' => (int) env('VENDOR_ASSISTANT_CONNECT_TIMEOUT', 5),
    'max_message_characters' => (int) env('VENDOR_ASSISTANT_MAX_MESSAGE_CHARACTERS', 4000),
    'max_context_messages' => (int) env('VENDOR_ASSISTANT_MAX_CONTEXT_MESSAGES', 20),
    'max_tool_rounds' => (int) env('VENDOR_ASSISTANT_MAX_TOOL_ROUNDS', 5),
    'max_tool_calls' => (int) env('VENDOR_ASSISTANT_MAX_TOOL_CALLS', 12),
];
