<?php

namespace App\Services;

use App\Contracts\OtpSender;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

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
            abort(503, 'تعذّر إرسال رمز التحقق. حاول لاحقًا.');
        }
        abort_unless($response->successful() && in_array((string) $response->json('code'), ['1', 'M0000'], true), 503, 'تعذّر إرسال رمز التحقق. حاول لاحقًا.');
    }
}
