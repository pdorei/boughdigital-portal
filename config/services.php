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

    /*
    | Claude (Anthropic) Messages API. The API key is entered in Admin >
    | Settings > Connections and stored encrypted; it is never read from here.
    | Prices are USD per million tokens and only estimate the monthly cap.
    */
    'anthropic' => [
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        'version' => '2026-10-21',
        'timeout' => 120,
        'default_model' => 'claude-sonnet-5-5',
        'default_fit_model' => 'claude-haiku-5-5',
        'default_monthly_cap_usd' => 50,
        // Re-runs a declined request on a fallback model inside the same call.
        'refusal_fallback_models' => ['claude-opus-5-5', 'claude-sonnet-5-5'],
        'models' => [
            'claude-opus-5-5' => ['label' => 'Claude Opus 5.5', 'input' => 4.00, 'output' => 20.00],
            'claude-sonnet-5-5' => ['label' => 'Claude Sonnet 5.5', 'input' => 2.00, 'output' => 10.00],
            'claude-haiku-5-5' => ['label' => 'Claude Haiku 5.5', 'input' => 0.10, 'output' => 0.50],
        ],
    ],

];
