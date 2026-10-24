<?php

namespace App\Observers;

use App\Jobs\SendFirebaseNotification;
use App\Models\DeviceToken;
use App\Models\Notification;

class NotificationObserver
{
    public function created(Notification $notification): void
    {
        if (! config('firebase.push_enabled') || ! in_array($notification->type, ['order_created', 'order_status'], true)) {
            return;
        }
        DeviceToken::where('user_id', $notification->user_id)->where('platform', 'android')->each(function ($device) use ($notification) {
            SendFirebaseNotification::dispatch($notification->id, $device->id, $device->token);
        });
    }
}
