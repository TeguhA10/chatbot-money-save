<?php

return [

    'midtrans' => [
        'server_key'       => env('MIDTRANS_SERVER_KEY'),
        'client_key'       => env('MIDTRANS_CLIENT_KEY'),
        'is_production'    => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        'enabled_payments' => env('MIDTRANS_ENABLED_PAYMENTS') ? explode(',', env('MIDTRANS_ENABLED_PAYMENTS')) : null,
    ],

    'whatsapp' => [
        'phone_number_id'     => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'access_token'        => env('WHATSAPP_ACCESS_TOKEN'),
        'verify_token'        => env('WHATSAPP_VERIFY_TOKEN'),
        'app_secret'          => env('META_APP_SECRET'),
        'api_version'         => env('WHATSAPP_API_VERSION', 'v21.0'),
        'bot_number'          => env('WHATSAPP_BOT_NUMBER', env('VITE_WHATSAPP_BOT_NUMBER', '6285111355992')),
        'gateway_url'         => env('GATEWAY_OUTBOUND_URL', env('GATEWAY_URL', env('WHATSAPP_GATEWAY_URL', 'http://gateway:3001'))),
    ],

    'groq' => [
        'key'           => env('GROQ_API_KEY'),
        'whisper_model' => env('GROQ_WHISPER_MODEL', 'whisper-large-v3'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

    'gemini' => [
        'key'   => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
    ],

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

];
