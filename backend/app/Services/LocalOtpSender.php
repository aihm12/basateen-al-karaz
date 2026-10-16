<?php

namespace App\Services;

use App\Contracts\OtpSender;

final class LocalOtpSender implements OtpSender
{
    public function send(string $phone, string $code): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('store.otp_driver') === 'local', 503, 'OTP provider is not configured.');
        // Local code is returned only by the development endpoint, never logged.
    }
}
