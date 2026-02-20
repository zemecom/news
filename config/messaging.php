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
        'enriched_ready' => 'enriched.ready',
        'enriched_ready_important' => 'enriched.ready.important',
        'enriched_rejected' => 'enriched.rejected',
    ],
    'queues' => [
        'delivery_feed' => 'queue.delivery_feed',
        'delivery_push' => 'queue.delivery_push',
    ],
];
