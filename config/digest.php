<?php

return [

    'timezone' => env('DIGEST_TIMEZONE', 'Europe/Paris'),

    'news_time' => env('DIGEST_NEWS_TIME', '09:00'),

    'learning_time' => env('DIGEST_LEARNING_TIME', '09:30'),

    'max_items' => (int) env('DIGEST_MAX_ITEMS', 20),

    'max_attempts' => (int) env('DIGEST_MAX_ATTEMPTS', 4),

    'profile' => env('DIGEST_PROFILE', ''),

    // Ordered failover chain, any provider from config/ai.php: "gemini:<model>,anthropic:<model>"
    'ai_providers' => env('DIGEST_AI_PROVIDERS', ''),

    'ai_timeout' => (int) env('DIGEST_AI_TIMEOUT', 120),

];
