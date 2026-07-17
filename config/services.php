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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ai' => [
        'default' => env('AI_PROVIDER', 'gemini'),
        'gemini' => [
            'key' => env('GEMINI_API_KEY'),
        ],
        'openai' => [
            'key' => env('OPENAI_API_KEY'),
        ],
        'claude' => [
            'key' => env('CLAUDE_API_KEY'),
        ],
        'azure' => [
            'key' => env('AZURE_OPENAI_API_KEY'),
            'endpoint' => env('AZURE_OPENAI_ENDPOINT'),
            'deployment' => env('AZURE_OPENAI_DEPLOYMENT_NAME'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2023-05-15'),
        ],
    ],

];
