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

    'cloudflare' => [
        'account_id' => ($acc = env('CLOUDFLARE_ACCOUNT_ID')) && ! in_array(strtolower((string) $acc), ['your_cloudflare_account_id', ''])
            ? $acc
            : '84753a3f8d0b1a36c7331cd95b48fc7c',
        'token' => ($tok = env('CLOUDFLARE_API_TOKEN')) && ! in_array(strtolower((string) $tok), ['your_cloudflare_api_token', ''])
            ? $tok
            : 'KV1CZKUaPZ-wLbPldwJzr-ar20yElWTJTR6OzpxL',
        'gateway' => ($gw = env('CLOUDFLARE_AI_GATEWAY')) && ! in_array(strtolower((string) $gw), ['your_cloudflare_ai_gateway_optional', 'your_cloudflare_ai_gateway', ''])
            ? $gw
            : null,
    ],

];
