<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API credentials
    |--------------------------------------------------------------------------
    |
    | Your secret API key (sk_live_... or sk_test_...). The key prefix decides
    | live vs test mode. The client is only built when first resolved, so a
    | missing key fails at that point, not at application boot.
    |
    */

    'api_key' => env('ASLAN_API_KEY'),

    'base_url' => env('ASLAN_BASE_URL', 'https://api.aslanpay.ma/pay'),

    // Request timeout in seconds.
    'timeout' => (int) env('ASLAN_TIMEOUT', 30),

    // Automatic retries on transient errors.
    'max_retries' => (int) env('ASLAN_MAX_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Aslan signs every delivery with the X-Webhook-Signature header
    | (t=<unix_seconds>,v1=<hmac_sha256_hex>). Deliveries older than
    | `tolerance` seconds are rejected (0 disables the age check).
    |
    */

    'webhook' => [
        'enabled' => (bool) env('ASLAN_WEBHOOK_ENABLED', true),
        'secret' => env('ASLAN_WEBHOOK_SECRET'),
        'tolerance' => (int) env('ASLAN_WEBHOOK_TOLERANCE', 300),
        'path' => env('ASLAN_WEBHOOK_PATH', 'aslan/webhook'),

        // Route middleware group. Keep it outside `web` (no session, no CSRF).
        'middleware' => env('ASLAN_WEBHOOK_MIDDLEWARE', 'api'),
    ],

];
