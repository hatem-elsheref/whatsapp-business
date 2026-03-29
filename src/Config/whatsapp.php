<?php

return [
    'name' => env('WA_APP_NAME', 'WhatsApp Business'),
    
    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'api_version' => env('META_API_VERSION', 'v18.0'),
        'graph_url' => 'https://graph.facebook.com',
    ],

    'oauth' => [
        'client_id' => env('META_APP_ID'),
        'client_secret' => env('META_APP_SECRET'),
        'redirect_uri' => env('META_OAUTH_REDIRECT_URI'),
        'auth_url' => 'https://www.facebook.com/v18.0/dialog/oauth',
        'token_url' => 'https://graph.facebook.com/v18.0/oauth/access_token',
    ],

    'webhook' => [
        'verify_token' => env('WA_WEBHOOK_VERIFY_TOKEN'),
        'route_prefix' => 'api/wa',
    ],

    'notifications' => [
        'pusher' => [
            'app_id' => env('PUSHER_APP_ID'),
            'app_key' => env('PUSHER_APP_KEY'),
            'app_secret' => env('PUSHER_APP_SECRET'),
            'cluster' => env('PUSHER_CLUSTER', 'mt1'),
            'useTLS' => env('PUSHER_USE_TLS', true),
        ],
    ],

    'encryption' => [
        'key' => env('WA_ENCRYPTION_KEY'),
    ],

    'defaults' => [
        'phone_number' => [
            'name' => null,
        ],
        'conversation' => [
            'window_expires_hours' => 24,
            'unread_threshold' => 0,
        ],
        'message' => [
            'max_media_size' => 16 * 1024 * 1024, // 16MB
            'supported_media_types' => [
                'image/jpeg',
                'image/png',
                'video/mp4',
                'audio/mpeg',
                'application/pdf',
            ],
        ],
    ],

    'seeder' => [
        'admin_email' => env('WA_ADMIN_EMAIL', 'admin@whatsapp.local'),
        'admin_password' => env('WA_ADMIN_PASSWORD', 'admin123'),
        'admin_name' => env('WA_ADMIN_NAME', 'Admin'),
        'customer_name' => env('WA_CUSTOMER_NAME', 'WhatsApp Business'),
        'customer_email' => env('WA_CUSTOMER_EMAIL', 'admin@whatsapp.local'),
    ],
];
