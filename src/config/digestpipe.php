<?php

return [
    'ai' => [
        'driver' => env('DIGESTPIPE_AI_DRIVER', 'fake'),
    ],

    'analysis' => [
        'model' => env('DIGESTPIPE_ANALYSIS_MODEL', env('OPENAI_MODEL', 'gpt-4o-mini')),
        'batch_limit' => (int) env('DIGESTPIPE_ANALYSIS_BATCH_LIMIT', 10),
        'daily_limit' => (int) env('DIGESTPIPE_ANALYSIS_DAILY_LIMIT', 100),
        'max_input_chars' => (int) env('DIGESTPIPE_ANALYSIS_MAX_INPUT_CHARS', 8000),
        'schema_version' => env('DIGESTPIPE_ANALYSIS_OUTPUT_SCHEMA_VERSION', '1.0'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'request_timeout' => (int) env('OPENAI_REQUEST_TIMEOUT', 60),
        'max_retries' => (int) env('OPENAI_MAX_RETRIES', 2),
    ],

    'content' => [
        'fetch_timeout' => (int) env('DIGESTPIPE_CONTENT_FETCH_TIMEOUT', 15),
        'max_bytes' => (int) env('DIGESTPIPE_CONTENT_MAX_BYTES', 1048576),
        'max_chars' => (int) env('DIGESTPIPE_CONTENT_MAX_CHARS', 8000),
        'min_chars' => (int) env('DIGESTPIPE_CONTENT_MIN_CHARS', 200),
        'user_agent' => env('DIGESTPIPE_CONTENT_USER_AGENT', 'digestpipe/0.1 (+structured digest pipeline)'),
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
