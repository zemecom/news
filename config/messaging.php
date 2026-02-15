<?php

declare(strict_types=1);

return [
    'exchange' => [
        'news_flow' => [
            'name' => 'news_flow',
            'type' => 'topic',
        ],
    ],
    'routing_keys' => [
        'raw_created' => 'raw.created',
        'raw_retry' => 'raw.retry',
        'enriched_ready' => 'enriched.ready',
        'enriched_ready_important' => 'enriched.ready.important',
        'enriched_rejected' => 'enriched.rejected',
    ],
    'queues' => [
        'raw_ingest' => 'queue.raw_ingest',
        'news_processing' => 'queue.news_processing',
        'delivery_feed' => 'queue.delivery_feed',
        'delivery_push' => 'queue.delivery_push',
        'news_processing_dlq' => 'queue.news_processing.dlq',
    ],
    'processing' => [
        'max_attempts' => 5,
        'retry_backoff_seconds' => [5, 15, 60],
    ],
];
