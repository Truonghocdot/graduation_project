<?php

return [
    'quote_ttl_seconds' => (int) env('QUOTE_TTL_SECONDS', 300),
    'rounding_unit' => (int) env('PRICING_ROUNDING_UNIT', 1_000),
];
