<?php

namespace App\Services;

use App\Contracts\OtpSender;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class MsegatOtpSender implements OtpSender
{
    public function send(string $phone, string $code): void
    {
        $settings = config('services.msegat');
        abort_unless(! empty($settings['username']) && ! empty($settings['api_key']) && ! empty($settings['sender']), 503, 'خدمة رمز التحقق غير متاحة حاليًا.');
        abort_unless(preg_match('/^\+9665[0-9]{8}$/', $phone) && preg_match('/^[0-9]{6}$/', $code), 422, 'بيانات التحقق غير صحيحة.');
        try {
            $response = Http::acceptJson()->connectTimeout(5)->timeout(15)->post('https://www.msegat.com/gw/sendsms.php', [
                'userName' => $settings['username'],
                'apiKey' => $settings['api_key'],
                'userSender' => $settings['sender'],
                'numbers' => ltrim($phone, '+'),
                'msg' => 'رمز التحقق: '.$code,
                'msgEncoding' => 'UTF8',
            ]);
        } catch (ConnectionException) {
            Log::warning('msegat_otp_transport_failed', ['endpoint' => 'sendsms.php']);
            abort(503, 'تعذّر إرسال رمز التحقق. حاول لاحقًا.');
        }
        $accepted = $response->successful() && in_array((string) $response->json('code'), ['1', 'M0000'], true);
        if (! $accepted) {
            // Only diagnostic fields; never log the request or arbitrary response fields.
            $redact = static function ($value) use ($settings, $phone, $code): ?string {
                if (! is_scalar($value)) {
                    return null;
                }
                $value = str_replace([$settings['api_key'], $settings['username'], $phone, ltrim($phone, '+'), $code], '[redacted]', (string) $value);

                return mb_substr(preg_replace('/[0-9]{6,}/', '[redacted]', $value), 0, 500);
            };
            Log::warning('msegat_otp_rejected', [
                'endpoint' => 'sendsms.php',
                'http_status' => $response->status(),
                'provider_code' => $redact($response->json('code')),
                'provider_message' => $redact($response->json('message')),
                'json_response' => is_array($response->json()),
            ]);
        }
        abort_unless($accepted, 503, 'تعذّر إرسال رمز التحقق. حاول لاحقًا.');
    }
}
