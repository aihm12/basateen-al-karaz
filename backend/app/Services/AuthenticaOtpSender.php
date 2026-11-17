<?php

namespace App\Services;

use App\Contracts\OtpSender;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class AuthenticaOtpSender implements OtpSender
{
    public function send(string $phone, string $code): void
    {
        // Authentica generates the OTP; the local random code is never sent or accepted.
        $response = $this->request('send-otp', [
            'phone' => $phone,
            'method' => 'sms',
            'template_id' => (int) config('services.authentica.template_id', 1),
        ]);
        abort_unless($response->successful() && $response->json('success') === true,
            503, 'تعذّر إرسال رمز التحقق. حاول لاحقًا.');
    }

    public function verify(string $phone, string $code): bool
    {
        $response = $this->request('verify-otp', ['phone' => $phone, 'otp' => $code]);
        abort_if($response->serverError() || in_array($response->status(), [401, 403, 429]),
            503, 'تعذّر التحقق من الرمز الآن. حاول لاحقًا.');

        return $response->successful() && $response->json('status') === true;
    }

    private function request(string $operation, array $payload): Response
    {
        $key = config('services.authentica.api_key');
        abort_unless(is_string($key) && trim($key) !== '', 503, 'خدمة رمز التحقق غير متاحة حاليًا.');
        abort_unless(preg_match('/^\+9665[0-9]{8}$/', $payload['phone']), 422, 'رقم الجوال غير صحيح.');
        try {
            $response = Http::acceptJson()->asJson()->withHeaders(['X-Authorization' => $key])
                ->connectTimeout(5)->timeout(15)
                ->post('https://api.authentica.sa/api/v2/'.$operation, $payload);
        } catch (ConnectionException) {
            Log::warning('authentica_transport_failed', ['operation' => $operation]);
            abort(503, 'تعذّر الاتصال بخدمة التحقق. حاول لاحقًا.');
        }
        if (! $response->successful() || $response->json($operation === 'send-otp' ? 'success' : 'status') !== true) {
            // Never log phone numbers, OTPs, credentials or provider response bodies.
            Log::warning('authentica_request_rejected', ['operation' => $operation, 'http_status' => $response->status()]);
        }

        return $response;
    }
}
