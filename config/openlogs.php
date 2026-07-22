<?php

return [

    // OpenLogs server base URL and the project API key.
    'url'     => env('OPENLOGS_URL'),
    'api_key' => env('OPENLOGS_API_KEY'),

    // Minimum level and buffering/transport tunables for the Monolog handler.
    'level'        => env('OPENLOGS_LEVEL', 'debug'),
    'buffer_limit' => (int) env('OPENLOGS_BUFFER_LIMIT', 500),
    'timeout'      => (float) env('OPENLOGS_TIMEOUT', 5.0),

    // Local Laravel channel that failed deliveries are replayed to. Must not be
    // the "openlogs" channel itself (guarded against to prevent a logging loop).
    'fallback_channel' => env('OPENLOGS_FALLBACK_CHANNEL', 'single'),

    // Optional queued delivery: dispatch each batch to a background job instead
    // of POSTing inline. Defaults to a dedicated "openlogs" queue — never the
    // application's default queue.
    'queue' => [
        'enabled'    => (bool) env('OPENLOGS_QUEUE', false),
        'connection' => env('OPENLOGS_QUEUE_CONNECTION'),
        'queue'      => env('OPENLOGS_QUEUE_NAME', 'openlogs'),
    ],

];
