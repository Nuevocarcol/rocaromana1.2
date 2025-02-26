<?php


defined('PERMISSION_ERROR_MSG') or define('PERMISSION_ERROR_MSG', 'You are not authorize to operate on the module ');
defined('JWT_SECRET_KEY') or define('JWT_SECRET_KEY', '404D635166546A576E5A7234753778214125442A472D4B614E645267556B5870');

defined('PAYPAL_SANDBOX_MODE') or define('PAYPAL_SANDBOX_MODE', true);
defined('sandbox') or define('sandbox', true);
//Sandbox
defined('business') or define('business', 'sb-uefcv23946367@business.example.com');

//Live
// defined('PAYPAL_LIVE_BUSINESS_EMAIL') or define('PAYPAL_LIVE_BUSINESS_EMAIL', '');
// defined('PAYPAL_CURRENCY') or define('PAYPAL_CURRENCY', 'USD');

return [
    'CACHE' => [
        'SYSTEM' => [
            'DEFAULT_LANGUAGE' => 'default_language',
            'SETTINGS' => 'systemSettings'
        ],
    ],
    'RESPONSE_CODE' => [
        'EXCEPTION_ERROR' => 500
    ]
];