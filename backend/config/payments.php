<?php

return [
    'publishable_key' => env('MOYASAR_PUBLISHABLE_KEY', ''),
    'enabled' => (bool) env('MOYASAR_ENABLED', false),
    'allow_cloud_test' => (bool) env('MOYASAR_ALLOW_CLOUD_TEST', false),
    'secret_key' => env('MOYASAR_SECRET_KEY', ''),
    'return_url' => env('MOYASAR_RETURN_URL', 'http://127.0.0.1:8000/app/'),
];
