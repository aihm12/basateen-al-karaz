<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\DeliverySlot;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(private CartService $cartService, private FoodicsIntegration $foodics) {}

    public function checkout(User $user, array $data, string $key): Order
    {
        if ($data['payment_method'] === 'moyasar' && ! app(MoyasarGateway::class)->enabled()) {
            $this->cartService->fail('payment_method', 'الدفع الإلكتروني غير مهيأ بعد.');
        }
        ksort($data);
        $hash = hash('sha256', json_encode($data));

        return DB::transaction(function () use ($user, $data, $key, $hash) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = $user->orders()->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_if($existing->request_hash !== $hash, 409, 'Idempotency key already used with different data');

                return $existing;
            }
            $cart = Cart::where('user_id', $user->id)->with('items.options')->lockForUpdate()->first();
            if (! $cart || $cart->items->isEmpty()) {
                $this->cartService->fail('cart', 'السلة فارغة.');
            }
            $branch = Branch::whereKey($cart->branch_id)->where('is_active', true)->firstOrFail();
            if (! ($data['delivery_type'] === 'pickup' ? $branch->pickup_enabled : $branch->delivery_enabled)) {
                $this->cartService->fail('delivery_type', 'الخدمة غير متاحة لهذا الفرع.');
            }
            $lines = [];
            $subtotal = 0;
            foreach ($cart->items->sortBy('variant_id') as $item) {
                $qty = $cart->items->where('variant_id', $item->variant_id)->sum('quantity');
                $quote = $this->cartService->quote($item->variant_id, $branch->id, $item->options->pluck('option_value_id')->all(), $qty);
                $line = $quote['unit'] * $item->quantity;
                $subtotal += $line;
                $lines[] = [$item, $quote, $line];
            }
            $discount = 0;
            $coupon = null;
            if (! empty($data['coupon_code'])) {
                $coupon = Coupon::where('code', strtoupper($data['coupon_code']))->lockForUpdate()->first();
                if (! $coupon || ! $coupon->is_active || ($coupon->starts_at && $coupon->starts_at->isFuture()) || ($coupon->expires_at && $coupon->expires_at->isPast()) || $subtotal < Money::cents($coupon->minimum_order)) {
                    $this->cartService->fail('coupon_code', 'الكوبون غير صالح.');
                }
                $uses = $coupon->orders()->where('status', '!=', 'cancelled');
                if (($coupon->usage_limit !== null && (clone $uses)->count() >= $coupon->usage_limit) || (clone $uses)->where('user_id', $user->id)->count() >= $coupon->usage_per_customer || ($coupon->first_order_only && $user->orders()->where('status', '!=', 'cancelled')->exists())) {
                    $this->cartService->fail('coupon_code', 'تم بلوغ حد استخدام الكوبون.');
                }
                $discount = $coupon->discount_type === 'fixed' ? Money::cents($coupon->discount_value) : (int) round($subtotal * Money::cents($coupon->discount_value) / 10000);
                $discount = min($discount, $subtotal, $coupon->maximum_discount !== null ? Money::cents($coupon->maximum_discount) : $subtotal);
            }
            $address = null;
            $slot = null;
            $fee = 0;
            if ($data['delivery_type'] === 'delivery') {
                $address = $user->addresses()->findOrFail($data['address_id']);
                $zone = DeliveryZone::whereKey($address->delivery_zone_id)->where('branch_id', $branch->id)->where('is_active', true)->first();
                if (! $zone || ! in_array($address->district, $zone->districts ?? [], true)) {
                    $this->cartService->fail('address_id', 'العنوان خارج مناطق التوصيل المعرفة.');
                }
                if ($subtotal - $discount < Money::cents($zone->minimum_order)) {
                    $this->cartService->fail('cart', 'الطلب أقل من الحد الأدنى للتوصيل.');
                }
                $fee = $zone->free_delivery_above !== null && $subtotal - $discount >= Money::cents($zone->free_delivery_above) ? 0 : Money::cents($zone->delivery_fee);
                $slot = DeliverySlot::whereKey($data['delivery_slot_id'])->where('branch_id', $branch->id)->where('is_active', true)->lockForUpdate()->first();
                $date = Carbon::parse($data['delivery_date']);
                if (! $slot || $slot->day_of_week != $date->dayOfWeek || ! Carbon::parse($data['delivery_date'].' '.$slot->start_time)->isFuture() || $slot->orders()->whereDate('delivery_date', $data['delivery_date'])->where('status', '!=', 'cancelled')->count() >= $slot->max_orders) {
                    $this->cartService->fail('delivery_slot_id', 'موعد التوصيل غير متاح.');
                }
            }
            // Catalog prices include tax. Allocate coupon cents deterministically before extracting tax.
            $tax = 0;
            $remainingDiscount = $discount;
            $remainingSubtotal = $subtotal;
            $taxes = [];
            foreach ($lines as $i => [$item,$q,$line]) {
                $share = $remainingSubtotal > 0 ? (int) round($remainingDiscount * $line / $remainingSubtotal) : 0;
                $remainingDiscount -= $share;
                $remainingSubtotal -= $line;
                $rate = Money::cents($q['variant']->product->tax_rate);
                $taxes[$i] = (int) round(($line - $share) * $rate / (10000 + $rate));
                $tax += $taxes[$i];
            }
            $order = Order::create(['order_number' => 'BK-'.Str::ulid(), 'user_id' => $user->id, 'branch_id' => $branch->id, 'address_id' => $address?->id, 'address_snapshot' => $address?->only(['label', 'maps_url', 'latitude', 'longitude', 'district', 'street', 'building_number', 'floor', 'apartment', 'instructions']), 'subtotal' => Money::decimal($subtotal), 'discount' => Money::decimal($discount), 'delivery_fee' => Money::decimal($fee), 'tax' => Money::decimal($tax), 'total' => Money::decimal($subtotal - $discount + $fee), 'payment_method' => $data['payment_method'], 'delivery_type' => $data['delivery_type'], 'delivery_date' => $slot ? $data['delivery_date'] : null, 'delivery_slot_id' => $slot?->id, 'delivery_slot_snapshot' => $slot?->only(['start_time', 'end_time']), 'coupon_id' => $coupon?->id, 'customer_notes' => $data['customer_notes'] ?? null, 'idempotency_key' => $key, 'request_hash' => $hash]);
            foreach ($lines as $i => [$item,$q,$line]) {
                $v = $q['variant'];
                $oi = $order->items()->create(['stock_reserved' => $q['availability']->stock_quantity !== null, 'product_id' => $v->product_id, 'variant_id' => $v->id, 'product_name' => $v->product->name_ar, 'variant_name' => $v->name_ar, 'quantity' => $item->quantity, 'unit_price' => Money::decimal($q['unit']), 'total_price' => Money::decimal($line), 'tax_rate' => $v->product->tax_rate, 'tax' => Money::decimal($taxes[$i]), 'foodics_product_id' => $v->foodics_product_id ?? $v->product->foodics_product_id]);
                foreach ($q['values'] as $value) {
                    $oi->options()->create(['option_name' => $value->group->name_ar, 'value_name' => $value->name_ar, 'price' => $value->additional_price, 'foodics_modifier_id' => $value->foodics_modifier_id]);
                }
                if ($q['availability']->stock_quantity !== null) {
                    $q['availability']->decrement('stock_quantity', $item->quantity);
                }
            }
            $order->payments()->create(['provider' => $data['payment_method'] === 'moyasar' ? 'moyasar' : 'manual', 'payment_method' => $data['payment_method'], 'amount' => $order->total, 'status' => 'pending']);
            if ($address) {
                $order->delivery()->create(['status' => 'pending', 'delivery_provider' => 'store']);
            }
            $user->notifications()->create(['title' => 'تم استلام طلبك', 'body' => $order->order_number, 'type' => 'order_created', 'reference_id' => (string) $order->id]);
            $this->foodics->queueOrder($order);
            $cart->items()->delete();

            return $order->refresh();
        }, 3);
    }
}
