<?php

return [
    'admin_key' => env('ADMIN_KEY', 'semicon-admin-2026'),

    /*
    | kb = rule-based only (0 API cost, ~50-80 tokens)
    | claude | openai | cursor = AI provider
    | auto = try knowledge base first, then fallback AI
    */
    'ai_provider' => env('CHATBOT_AI_PROVIDER', 'auto'),

    'max_response_tokens' => (int) env('CHATBOT_MAX_RESPONSE_TOKENS', 100),

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-3-5-haiku-20241022'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'cursor_cli' => [
        'enabled' => env('CURSOR_CLI_ENABLED', false),
        'command' => env('CURSOR_CLI_COMMAND', 'cursor agent --print --output-format text'),
        'timeout' => (int) env('CURSOR_CLI_TIMEOUT', 60),
    ],

    'knowledge_path' => storage_path('app/knowledge.json'),

    'memory_turns' => (int) env('CHATBOT_MEMORY_TURNS', 6),

    'auto_learn' => env('CHATBOT_AUTO_LEARN', true),

    'learn_min_hits' => (int) env('CHATBOT_LEARN_MIN_HITS', 2),
];
