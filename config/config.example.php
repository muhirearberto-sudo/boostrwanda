<?php
/**
 * BoostRwanda configuration (EXAMPLE - copy to config.php and edit).
 * Copy of config.example.php with local-development values.
 * For any real deployment: set a DB password, change the webhook secret,
 * keep 'debug' => false and serve over HTTPS.
 */
return [
    'app_name'  => 'BoostRwanda',
    'base_url'  => '/BoostRwanda',          // URL path where the app lives under htdocs
    'currency'  => 'RWF',
    'debug'     => false,                   // true shows PHP errors (development only)

    'db' => [
        'host'    => '127.0.0.1',
        'name'    => 'boostrwanda_db',
        'user'    => 'root',                // XAMPP default
        'pass'    => '',                    // XAMPP default is an empty password
        'charset' => 'utf8mb4',
    ],

    // File uploads (task proof)
    'upload_max_bytes' => 2 * 1024 * 1024,  // 2 MB
    'upload_allowed'   => [                 // extension => real MIME type
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',  'pdf'  => 'application/pdf',
    ],

    // Login throttling
    'max_login_attempts' => 5,
    'lockout_minutes'    => 15,
    'session_idle_secs'  => 7200,

    // Payment gateway (SANDBOX ONLY - no real money moves)
    'payment' => [
        'mode'           => 'sandbox',
        'webhook_secret' => 'change-me-sandbox-secret',  // HMAC key for api/payment_webhook.php
    ],
];
