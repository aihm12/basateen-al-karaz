<?php

namespace Tests\Feature;

use App\Jobs\SendFirebaseNotification;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\User;
use App\Services\FirebaseMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_notification_queues_only_owners_android_devices_after_commit(): void
    {
        Queue::fake();
        config(['firebase.push_enabled' => true]);
        $user = User::create(['name' => 'Customer', 'phone' => '+966500000001']);
        $other = User::create(['name' => 'Other', 'phone' => '+966500000002']);
        $device = DeviceToken::create(['user_id' => $user->id, 'token' => 'target', 'platform' => 'android']);
        DeviceToken::create(['user_id' => $other->id, 'token' => 'other', 'platform' => 'android']);
        $notification = $user->notifications()->create(['title' => 'Order', 'body' => 'Ready', 'type' => 'order_status', 'reference_id' => '4']);
        Queue::assertPushed(SendFirebaseNotification::class, fn ($job) => $job->notificationId === $notification->id && $job->deviceId === $device->id && $job->afterCommit === true);
        Queue::assertPushed(SendFirebaseNotification::class, 1);
        $device->update(['user_id' => $other->id]);
        $sender = $this->mock(FirebaseMessagingService::class);
        $sender->shouldNotReceive('send');
        (new SendFirebaseNotification($notification->id, $device->id, 'target'))->handle($sender);
    }

    public function test_unregistered_device_is_removed_and_disabled_feature_does_not_queue(): void
    {
        Queue::fake();
        config(['firebase.push_enabled' => false]);
        $user = User::create(['name' => 'Customer', 'phone' => '+966500000001']);
        $device = DeviceToken::create(['user_id' => $user->id, 'token' => 'target', 'platform' => 'android']);
        $notification = Notification::create(['user_id' => $user->id, 'title' => 'Order', 'body' => 'Ready', 'type' => 'order_status', 'reference_id' => '4']);
        Queue::assertNothingPushed();
        config(['firebase.push_enabled' => true]);
        $sender = $this->mock(FirebaseMessagingService::class);
        $sender->shouldReceive('send')->once()->andReturn(false);
        (new SendFirebaseNotification($notification->id, $device->id, 'target'))->handle($sender);
        $this->assertDatabaseMissing('device_tokens', ['id' => $device->id]);
    }
}
