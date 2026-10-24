<?php

namespace Tests\Feature;

use App\Services\FirebaseMessagingService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class FirebaseMessagingTest extends TestCase
{
    public function test_rejects_credentials_for_another_project_without_network_calls(): void
    {
        Http::preventStrayRequests();
        config(['firebase.credentials_base64' => base64_encode(json_encode(['type' => 'service_account', 'project_id' => 'wrong']))]);
        $this->expectException(RuntimeException::class);
        (new FirebaseMessagingService)->accessToken();
    }

    private function sender(): FirebaseMessagingService
    {
        return new class extends FirebaseMessagingService
        {
            public function accessToken(): string
            {
                return 'test-access';
            }
        };
    }

    public function test_sends_installation_id_and_string_data_using_authorization(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'accepted'])]);
        $this->assertTrue($this->sender()->send('test-fid', 'Title', 'Body', ['order_id' => '4']));
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-access')
            && $request['message']['fid'] === 'test-fid'
            && $request['message']['data']['order_id'] === '4'
            && $request['validate_only'] === false);
    }

    public function test_only_unregistered_device_is_treated_as_removable(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::sequence()->push(['error' => ['details' => [
            ['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED'],
        ]]], 404)->push([], 403)]);
        $this->assertFalse($this->sender()->send('test-fid', 'Title', 'Body'));
        Http::fake(['fcm.googleapis.com/*' => Http::response([], 403)]);
        $this->expectException(RuntimeException::class);
        $this->sender()->send('test-fid', 'Title', 'Body');
    }
}
