<?php

return [
    'ai' => [
        'driver' => env('DIGESTPIPE_AI_DRIVER', 'fake'),
        'batch_limit' => (int) env('DIGESTPIPE_AI_BATCH_LIMIT', 3),
        'daily_limit' => (int) env('DIGESTPIPE_AI_DAILY_LIMIT', 30),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'request_timeout' => (int) env('OPENAI_REQUEST_TIMEOUT', 60),
        'max_retries' => (int) env('OPENAI_MAX_RETRIES', 2),
    ],

    'feed_sources' => [
        [
            'key' => 'hacker_news',
            'name' => 'Hacker News',
            'url' => 'https://news.ycombinator.com/rss',
            'language' => 'en',
            'enabled' => true,
        ],
        [
            'key' => 'reuters_top',
            'name' => 'Reuters Top News',
            'url' => 'https://assets.wor.jp/rss/rdf/reuters/top.rdf',
            'language' => 'en',
            'enabled' => true,
        ],
    ],
];
