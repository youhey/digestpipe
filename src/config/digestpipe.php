<?php

$adminAllowedEmails = env('DIGESTPIPE_ADMIN_ALLOWED_EMAILS', '');

if (! is_string($adminAllowedEmails)) {
    $adminAllowedEmails = '';
}

return [
    'ai' => [
        'driver' => env('DIGESTPIPE_AI_DRIVER', 'fake'),
    ],

    'admin' => [
        'allowed_emails' => array_values(array_filter(array_map(
            static fn (string $email): string => trim($email),
            explode(',', $adminAllowedEmails),
        ), static fn (string $email): bool => $email !== '')),
        'dev_login' => [
            'enabled' => (bool) env('DIGESTPIPE_ADMIN_DEV_LOGIN_ENABLED', false),
            'email' => env('DIGESTPIPE_ADMIN_DEV_LOGIN_EMAIL'),
        ],
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

    'translation' => [
        'driver' => env('DIGESTPIPE_TRANSLATION_DRIVER', 'none'),
        'target_language' => env('DIGESTPIPE_TRANSLATION_TARGET_LANGUAGE', 'JA'),
        'max_chars' => (int) env('DIGESTPIPE_TRANSLATION_MAX_CHARS', 8000),
    ],

    'deepl' => [
        'api_key' => env('DEEPL_API_KEY'),
    ],

    'content' => [
        'fetch_timeout' => (int) env('DIGESTPIPE_CONTENT_FETCH_TIMEOUT', 15),
        'max_bytes' => (int) env('DIGESTPIPE_CONTENT_MAX_BYTES', 1048576),
        'max_chars' => (int) env('DIGESTPIPE_CONTENT_MAX_CHARS', 8000),
        'min_chars' => (int) env('DIGESTPIPE_CONTENT_MIN_CHARS', 200),
        'user_agent' => env('DIGESTPIPE_CONTENT_USER_AGENT', 'digestpipe/0.1 (+structured digest pipeline)'),
    ],

    'selection' => [
        'enabled' => true,
        'default_score' => 0,
        'analysis_threshold' => 1,
        'skip_threshold' => -50,
    ],
];
