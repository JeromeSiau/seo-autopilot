<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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
    |--------------------------------------------------------------------------
    | LLM Provider (OpenRouter - unified access to all models)
    |--------------------------------------------------------------------------
    */

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings (Voyage AI)
    |--------------------------------------------------------------------------
    */

    'voyage' => [
        'api_key' => env('VOYAGE_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google OAuth (Search Console & GA4)
    |--------------------------------------------------------------------------
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Generation (Replicate - FLUX models)
    |--------------------------------------------------------------------------
    */

    'replicate' => [
        'api_key' => env('REPLICATE_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO Data
    |--------------------------------------------------------------------------
    */

    'dataforseo' => [
        'login' => env('DATAFORSEO_LOGIN'),
        'password' => env('DATAFORSEO_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment
    |--------------------------------------------------------------------------
    */

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'site_indexer' => [
        'storage_path' => env('SITE_INDEXER_STORAGE_PATH', storage_path('indexes')),
    ],

    'ploi' => [
        'token' => env('PLOI_API_TOKEN'),
        'server_id' => env('PLOI_SERVER_ID'),
        'site_id' => env('PLOI_SITE_ID'),
        'webhook_token' => env('PLOI_WEBHOOK_TOKEN'),
    ],

    'hosted' => [
        'staging_base_domain' => env('HOSTED_STAGING_BASE_DOMAIN'),
        'public_ip' => env('HOSTED_PUBLIC_IP'),
        'cname_target' => env('HOSTED_CNAME_TARGET'),
        'primary_domains' => array_filter(array_map('trim', explode(',', (string) env(
            'APP_PRIMARY_DOMAINS',
            parse_url((string) env('APP_URL', ''), PHP_URL_HOST) ?: ''
        )))),
    ],

];
