<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_registration_requires_login(): void
    {
        $this->putJson('/api/device-token', ['token' => 'device-token', 'platform' => 'android'])->assertUnauthorized();
    }

    public function test_registration_transfers_device_and_previous_user_cannot_remove_it(): void
    {
        $first = User::create(['name' => 'First', 'phone' => '+966500000001']);
        $second = User::create(['name' => 'Second', 'phone' => '+966500000002']);
        $data = ['token' => 'test-device-token', 'platform' => 'android'];
        Sanctum::actingAs($first);
        $this->putJson('/api/device-token', $data)->assertNoContent();
        $this->putJson('/api/device-token', $data)->assertNoContent();
        $this->assertDatabaseCount('device_tokens', 1);
        Sanctum::actingAs($second);
        $this->putJson('/api/device-token', $data)->assertNoContent();
        Sanctum::actingAs($first);
        $this->deleteJson('/api/device-token', $data)->assertNoContent();
        $this->assertDatabaseHas('device_tokens', ['token' => $data['token'], 'user_id' => $second->id]);
        Sanctum::actingAs($second);
        $this->deleteJson('/api/device-token', $data)->assertNoContent();
        $this->assertDatabaseCount('device_tokens', 0);
    }
}
