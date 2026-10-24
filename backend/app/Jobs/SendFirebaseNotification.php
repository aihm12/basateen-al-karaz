<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\Notification;
use App\Services\FirebaseMessagingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendFirebaseNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public function __construct(public int $notificationId, public int $deviceId, public string $target)
    {
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(FirebaseMessagingService $firebase): void
    {
        if (! config('firebase.push_enabled')) {
            return;
        }
        $notification = Notification::find($this->notificationId);
        if (! $notification) {
            return;
        }
        $device = DeviceToken::whereKey($this->deviceId)->where('user_id', $notification->user_id)
            ->where('token', $this->target)->first();
        if (! $device || ! $device->user?->is_active) {
            return;
        }
        $sent = $firebase->send($device->token, $notification->title, $notification->body, [
            'notification_id' => (string) $notification->id,
            'order_id' => (string) $notification->reference_id,
            'type' => $notification->type,
        ]);
        if (! $sent) {
            DeviceToken::whereKey($device->id)->where('user_id', $notification->user_id)->where('token', $this->target)->delete();
        }
    }
}
