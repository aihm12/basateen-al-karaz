<?php

return [
    'enabled' => env('FIREBASE_AUTH_ENABLED', false),
    'push_enabled' => env('FIREBASE_PUSH_ENABLED', false),
    'project_id' => env('FIREBASE_PROJECT_ID', 'basateen-al-karaz'),
    'credentials_base64' => env('FIREBASE_CREDENTIALS_BASE64'),
    'credentials_path' => env('FIREBASE_CREDENTIALS_PATH'),
];
