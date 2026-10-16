<?php

return [
    'enabled' => (bool) env('MOYASAR_ENABLED', false),
    'secret_key' => env('MOYASAR_SECRET_KEY', ''),
    'return_url' => env('MOYASAR_RETURN_URL', 'http://127.0.0.1:8000/app/'),
];
