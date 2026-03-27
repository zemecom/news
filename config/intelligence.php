<?php

declare(strict_types=1);

return [
    'provider' => env('LLM_PROVIDER', 'chatgpt_codex'),
    'fallback_provider' => env('LLM_FALLBACK_PROVIDER', 'heuristic'),

    'categories' => [
        'IT',
        'Экономика',
        'Политика',
        'Бытовой криминал',
        'Наука',
        'Мир',
        'Общество',
        'Спорт',
        'Культура',
        'Здоровье',
        'Другое',
    ],

    'chatgpt_codex' => [
        'binary' => env('CODEX_BINARY', 'codex'),
        'model' => env('LLM_CHATGPT_CODEX_MODEL', 'gpt-5.4-mini'),
        'reasoning_effort' => env('LLM_CHATGPT_CODEX_REASONING_EFFORT'),
        'available_models' => [
            'gpt-5.4' => 'GPT-5.4',
            'gpt-5.4-mini' => 'GPT-5.4-Mini',
            'gpt-5.3-codex' => 'GPT-5.3-Codex',
            'gpt-5.3-codex-spark' => 'GPT-5.3-Codex-Spark (ChatGPT Pro preview)',
            'gpt-5.2-codex' => 'GPT-5.2-Codex',
            'gpt-5.2' => 'GPT-5.2',
            'gpt-5.1-codex-max' => 'GPT-5.1-Codex-Max',
            'gpt-5.1-codex-mini' => 'GPT-5.1-Codex-Mini',
        ],
        'available_models_by_plan' => [
            'plus' => [
                'gpt-5.4',
                'gpt-5.4-mini',
                'gpt-5.3-codex',
                'gpt-5.2-codex',
                'gpt-5.2',
                'gpt-5.1-codex-max',
                'gpt-5.1-codex-mini',
            ],
            'pro' => [
                'gpt-5.4',
                'gpt-5.4-mini',
                'gpt-5.3-codex',
                'gpt-5.3-codex-spark',
                'gpt-5.2-codex',
                'gpt-5.2',
                'gpt-5.1-codex-max',
                'gpt-5.1-codex-mini',
            ],
        ],
        'available_reasoning_efforts' => [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'xhigh' => 'XHigh',
        ],
        'available_reasoning_efforts_by_model' => [
            'gpt-5.4' => ['low', 'medium', 'high', 'xhigh'],
            'gpt-5.4-mini' => ['low', 'medium', 'high', 'xhigh'],
            'gpt-5.3-codex' => ['low', 'medium', 'high', 'xhigh'],
            'gpt-5.3-codex-spark' => ['low', 'medium', 'high'],
            'gpt-5.2-codex' => ['low', 'medium', 'high', 'xhigh'],
            'gpt-5.2' => ['low', 'medium', 'high', 'xhigh'],
            'gpt-5.1-codex-max' => ['low', 'medium', 'high', 'xhigh'],
            'gpt-5.1-codex-mini' => ['medium', 'high'],
        ],
        'timeout_seconds' => (int) env('LLM_CHATGPT_CODEX_TIMEOUT_SECONDS', 90),
        'scratch_dir' => env('LLM_CHATGPT_SCRATCH_DIR', '/tmp/codex-news-analysis'),
        'home_base' => env('CODEX_HOME_BASE', storage_path('app/.codex/providers')),
        'app_server_timeout_seconds' => (int) env('LLM_CHATGPT_CODEX_APP_SERVER_TIMEOUT_SECONDS', 15),
        'concurrency_cache_store' => env('LLM_CHATGPT_CODEX_CONCURRENCY_CACHE_STORE', 'redis'),
        'release_delay_seconds' => (int) env('LLM_CHATGPT_CODEX_RELEASE_DELAY_SECONDS', 5),
        'protocol_version' => 2,
        'client_name' => env('APP_NAME', 'SmartNews'),
        'client_version' => '1.0.0',
    ],
];
