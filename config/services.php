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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'woocommerce' => [
        'url' => env('WOOCOMMERCE_URL'),
        'consumer_key' => env('WOOCOMMERCE_CONSUMER_KEY'),
        'consumer_secret' => env('WOOCOMMERCE_CONSUMER_SECRET'),
    ],

    'stripe' => [
        'key'    => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    // Written to .env by Integrations → QuickBooks Sync (QuickBooksController::saveSettings);
    // the keys match the settings form's field names.
    'quickbooks' => [
        'client_id'           => env('QUICKBOOKS_CLIENT_ID'),
        'client_secret'       => env('QUICKBOOKS_CLIENT_SECRET'),
        'redirect'            => env('QUICKBOOKS_REDIRECT_URI'),
        'env'                 => env('QUICKBOOKS_ENV', 'Development'),
        'realm_id'            => env('QUICKBOOKS_REALM_ID'),
        'income_account_name' => env('QUICKBOOKS_INCOME_ACCOUNT_NAME'),
    ],

    // AI Assistant: optional server-level Claude key, used when none is saved
    // under AI Assistant → Settings (read through config so config:cache works).
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],


];
