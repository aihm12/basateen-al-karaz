<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebaseMessagingService
{
    public function accessToken(): string
    {
        $encoded = config('firebase.credentials_base64');
        $path = config('firebase.credentials_path');
        $json = $encoded ? base64_decode($encoded, true) : ($path && is_readable($path) ? file_get_contents($path) : false);
        $credentials = $json ? json_decode($json, true) : null;
        if (! is_array($credentials) || ($credentials['type'] ?? '') !== 'service_account'
            || ($credentials['project_id'] ?? '') !== config('firebase.project_id')
            || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException('Firebase credentials are missing, invalid, or belong to another project.');
        }

        return Cache::remember('firebase-access:'.hash('sha256', $json), 3000, function () use ($credentials): string {
            $encode = fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
            $header = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $payload = $encode(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => time(), 'exp' => time() + 3600,
            ], JSON_THROW_ON_ERROR));
            if (! openssl_sign($header.'.'.$payload, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Firebase credential signing failed.');
            }
            $response = Http::asForm()->connectTimeout(5)->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $header.'.'.$payload.'.'.$encode($signature),
            ]);
            if (! $response->successful() || ! is_string($response->json('access_token'))) {
                throw new RuntimeException('Firebase authentication failed (HTTP '.$response->status().').');
            }

            return $response->json('access_token');
        });
    }

    /** @param array<string, string> $data */
    public function send(string $installationId, string $title, string $body, array $data = [], bool $validateOnly = false): bool
    {
        $message = ['fid' => $installationId, 'notification' => ['title' => $title, 'body' => $body]];
        if ($data !== []) {
            $message['data'] = $data;
        }
        $response = Http::withToken($this->accessToken())->connectTimeout(5)->timeout(15)
            ->post('https://fcm.googleapis.com/v1/projects/'.rawurlencode(config('firebase.project_id')).'/messages:send', [
                'validate_only' => $validateOnly, 'message' => $message,
            ]);
        if ($response->successful()) {
            return true;
        }
        $unregistered = collect($response->json('error.details', []))->contains(fn ($detail) => ($detail['@type'] ?? '') === 'type.googleapis.com/google.firebase.fcm.v1.FcmError'
            && ($detail['errorCode'] ?? '') === 'UNREGISTERED');
        if ($unregistered) {
            return false;
        }
        throw new RuntimeException('Firebase sending failed (HTTP '.$response->status().').');
    }
}
