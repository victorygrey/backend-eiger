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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'care' => [
        'master_url' => env('CARE_MASTER_URL', 'https://care-master-dev.pos-eigerindo.com'),
        'wms_url' => env('CARE_WMS_URL', 'https://care-wms-dev.pos-eigerindo.com'),
        'legacy_url' => env('CARE_SIMULATOR_URL', 'http://127.0.0.1:8002'),
        'server_key' => env('CARE_SERVER_KEY', '$2y$10$1HIh4X/8NlSCknqwxkTog.990d3glvP2QoYahSVRJOs/uie7ph/fC'),
        'store_code' => env('CARE_STORE_CODE', '2022'),
        'timeout' => env('CARE_TIMEOUT', 10),
    ],

];
