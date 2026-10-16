<?php

return ['otp_driver' => env('OTP_DRIVER', 'local'), 'otp_ttl' => 5, 'token_days' => 30, 'foodics_enabled' => (bool) env('FOODICS_ENABLED', false), 'foodics_token' => env('FOODICS_TOKEN')];
