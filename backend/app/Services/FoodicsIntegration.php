<?php

namespace App\Services;

use App\Models\IntegrationLog;
use App\Models\Order;

class FoodicsIntegration
{
    public function queueOrder(Order $order): IntegrationLog
    {
        // Local outbox only. A future adapter/worker maps catalog IDs, authenticates,
        // redacts payloads, retries idempotently and marks actual Foodics responses.
        return IntegrationLog::create(['provider' => 'foodics', 'action' => 'create_order', 'direction' => 'out', 'reference_type' => 'order', 'reference_id' => $order->id, 'request' => ['order_number' => $order->order_number], 'status' => 'deferred', 'error' => 'Foodics adapter is not configured']);
    }
}
