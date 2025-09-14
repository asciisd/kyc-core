<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default KYC Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default KYC verification driver that will be used
    | by the KYC manager. You may set this to any of the drivers defined in the
    | "drivers" array below.
    |
    */

    'default_driver' => env('KYC_DEFAULT_DRIVER', 'shuftipro'),

    /*
    |--------------------------------------------------------------------------
    | KYC Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the KYC drivers for your application. Each driver
    | has its own configuration options. You may add as many drivers as needed.
    |
    */

    'drivers' => [
        'shuftipro' => [
            'name' => 'ShuftiPro',
            'description' => 'ShuftiPro Identity Verification Service',
            'enabled' => env('SHUFTIPRO_ENABLED', true),
            'class' => env('SHUFTIPRO_DRIVER_CLASS', 'Asciisd\\KycShuftiPro\\Drivers\\ShuftiProDriver'),
            'supports' => [
                'document_verification' => true,
                'face_verification' => true,
                'address_verification' => true,
                'background_checks' => true,
                'age_verification' => true,
                'journey_verification' => true,
                'direct_api' => true,
                'webhook_callbacks' => true,
                'document_download' => true,
            ],
        ],

        'jumio' => [
            'name' => 'Jumio',
            'description' => 'Jumio Identity Verification Service',
            'enabled' => env('JUMIO_ENABLED', false),
            'class' => env('JUMIO_DRIVER_CLASS', 'Asciisd\\KycJumio\\Drivers\\JumioDriver'),
            'supports' => [
                'document_verification' => true,
                'face_verification' => true,
                'address_verification' => false,
                'background_checks' => false,
                'age_verification' => true,
                'journey_verification' => false,
                'direct_api' => true,
                'webhook_callbacks' => true,
                'document_download' => true,
            ],
        ],

        'onfido' => [
            'name' => 'Onfido',
            'description' => 'Onfido Identity Verification Service',
            'enabled' => env('ONFIDO_ENABLED', false),
            'class' => env('ONFIDO_DRIVER_CLASS', 'Asciisd\\KycOnfido\\Drivers\\OnfidoDriver'),
            'supports' => [
                'document_verification' => true,
                'face_verification' => true,
                'address_verification' => false,
                'background_checks' => true,
                'age_verification' => false,
                'journey_verification' => false,
                'direct_api' => true,
                'webhook_callbacks' => true,
                'document_download' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | KYC Settings
    |--------------------------------------------------------------------------
    |
    | General KYC configuration options that apply across all drivers.
    |
    */

    'settings' => [
        'require_email_verification' => env('KYC_REQUIRE_EMAIL_VERIFICATION', true),
        'max_verification_attempts' => env('KYC_MAX_ATTEMPTS', 3),
        'verification_url_expiry_hours' => env('KYC_URL_EXPIRY_HOURS', 24),
        'auto_download_documents' => env('KYC_AUTO_DOWNLOAD_DOCUMENTS', true),
        'document_storage_disk' => env('KYC_DOCUMENT_STORAGE_DISK', 's3'),
        'document_storage_path' => env('KYC_DOCUMENT_STORAGE_PATH', 'kyc/documents'),
        'enable_duplicate_detection' => env('KYC_ENABLE_DUPLICATE_DETECTION', true),
        'webhook_signature_validation' => env('KYC_WEBHOOK_SIGNATURE_VALIDATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Countries
    |--------------------------------------------------------------------------
    |
    | Define which countries are supported for KYC verification. This can be
    | overridden per driver if needed.
    |
    */

    'supported_countries' => [
        // Add ISO country codes for supported countries
        'US', 'GB', 'CA', 'AU', 'DE', 'FR', 'IT', 'ES', 'NL', 'BE',
        'CH', 'AT', 'SE', 'NO', 'DK', 'FI', 'IE', 'PT', 'LU', 'MT',
        'CY', 'EE', 'LV', 'LT', 'SI', 'SK', 'CZ', 'HU', 'PL', 'RO',
        'BG', 'HR', 'GR', 'JP', 'SG', 'HK', 'NZ', 'AE', 'SA', 'QA',
    ],

    /*
    |--------------------------------------------------------------------------
    | Restricted Countries
    |--------------------------------------------------------------------------
    |
    | Define which countries are restricted from KYC verification due to
    | regulatory or business requirements.
    |
    */

    'restricted_countries' => [
        // Add ISO country codes for restricted countries
        'IR', 'KP', 'SY', 'CU', 'MM', 'BY', 'VE', 'AF', 'IQ', 'LY',
    ],
];
