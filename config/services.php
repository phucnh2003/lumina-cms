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
    |--------------------------------------------------------------------------
    | Google Search Console
    |--------------------------------------------------------------------------
    |
    | Used to check a page's real Google index status via the URL Inspection
    | API. Requires a Google Cloud service account added as a user on the
    | Search Console property for the frontend's domain — see
    | GoogleSearchConsoleService for what each value is used for.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Social Login (Laravel Socialite)
    |--------------------------------------------------------------------------
    |
    | Driver config for the OAuth redirect flow used by plugins/social. This
    | is distinct from plugins/social/configs/social.php (unrelated plugin
    | config merged under config('social.*')) — Socialite's driver resolution
    | always reads config('services.<provider>').
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_APP_ID'),
        'client_secret' => env('FACEBOOK_APP_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

    'google_search_console' => [
        // Path to the service account JSON key file (keep it outside the repo / gitignored, e.g. storage/app/private/).
        'credentials_path' => env('GOOGLE_SEARCH_CONSOLE_CREDENTIALS_PATH'),
        // The Search Console property, e.g. "https://vietnamsolar.com.vn/" (must match exactly, trailing slash included).
        'site_url' => env('GOOGLE_SEARCH_CONSOLE_SITE_URL'),
    ],

];
