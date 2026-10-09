<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'openai' => [
        'enabled' => (bool) env('OPENAI_ENABLED', false),
        'key' => env('OPENAI_API_KEY'),
        'project_id' => env('OPENAI_PROJECT_ID'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', 'gpt-6-sol'),
        'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'high'),
        'prompt_version' => env('OPENAI_PROMPT_VERSION', 'lpj-intelligence-v1'),
        'connect_timeout' => (int) env('OPENAI_CONNECT_TIMEOUT', 10),
        'timeout' => (int) env('OPENAI_TIMEOUT', env('OPENAI_TIMEOUT_SECONDS', 240)),
        'max_retries' => (int) env('OPENAI_MAX_RETRIES', 2),
        'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 20_000),
        'daily_budget' => (float) env('OPENAI_DAILY_BUDGET', 0),
        'monthly_budget' => (float) env('OPENAI_MONTHLY_BUDGET', 0),
        'video' => [
            'frame_count' => (int) env('OPENAI_VIDEO_FRAME_COUNT', 48),
            'frame_max_width' => (int) env('OPENAI_VIDEO_FRAME_MAX_WIDTH', 1280),
            'frame_detail' => env('OPENAI_VIDEO_FRAME_DETAIL', 'high'),
        ],
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
