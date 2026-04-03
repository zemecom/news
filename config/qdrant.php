<?php

declare(strict_types=1);

return [

    'url' => rtrim((string) env('QDRANT_URL', 'http://qdrant:6333'), '/'),

    'api_key' => env('QDRANT_API_KEY'),

    'timeout' => (float) env('QDRANT_TIMEOUT', 3.0),

];
