<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MoyasarGateway
{
    public function enabled(): bool
    {
        return config('payments.enabled') && app()->environment(['local', 'testing'])
            && str_starts_with((string) config('payments.secret_key'), 'sk_test_');
    }

    public function request(string $method, string $path, array $payload = []): array
    {
        abort_unless($this->enabled(), 503, 'الدفع التجريبي غير مهيأ. يلزم مفتاح ميسر التجريبي في الخادم.');
        $response = Http::withBasicAuth(config('payments.secret_key'), '')
            ->acceptJson()->asJson()->connectTimeout(5)->timeout(15)
            ->send($method, 'https://api.moyasar.com/v1/'.$path, ['json' => $payload]);
        abort_unless($response->successful(), 502, 'تعذّر التواصل مع بوابة الدفع.');
        $data = $response->json();
        abort_unless(is_array($data), 502, 'استجابة دفع غير صالحة.');

        return $data;
    }
}
