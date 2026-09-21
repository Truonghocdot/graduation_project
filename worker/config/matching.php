<?php

return [
    'presence_ttl_seconds' => (int) env('MATCHING_PRESENCE_TTL_SECONDS', 15),
    'search_radius_meters' => (float) env('MATCHING_SEARCH_RADIUS_METERS', 5_000),
    'batch_size' => (int) env('MATCHING_BATCH_SIZE', 5),
    'offer_ttl_seconds' => (int) env('MATCHING_OFFER_TTL_SECONDS', 30),
    'outbox_channel' => env('MATCHING_OUTBOX_CHANNEL', 'worker.outbox'),
    'location_channel' => env('DRIVER_LOCATION_CHANNEL', 'worker.location'),
];
