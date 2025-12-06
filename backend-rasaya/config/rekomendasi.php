<?php

return [
    // Minimal sentiment score to trigger normal recommendations
    'min_sentiment' => env('REKOM_MIN_SENTIMENT', -0.05),

    // Enable widening recommendation range when below min_sentiment
    'fallback_enabled' => env('REKOM_FALLBACK_ENABLED', true),

    // +/- tolerance window around the actual score when fallback is used
    'fallback_tolerance' => env('REKOM_FALLBACK_TOLERANCE', 0.25),

    // Max number of fallback candidates to create per analysis entry
    'max_fallback' => env('REKOM_MAX_FALLBACK', 5),
];
