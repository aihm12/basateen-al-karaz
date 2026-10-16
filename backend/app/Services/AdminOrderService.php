<?php

namespace App\Services;

use App\Models\BranchProductVariant;
use App\Models\Order;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminOrderService
{
    public const LABELS = ['pending' => 'جديد', 'confirmed' => 'مؤكد', 'preparing' => 'قيد التجهيز', 'ready' => 'جاهز', 'assigned' => 'مسند للتوصيل', 'out_for_delivery' => 'في الطريق', 'delivered' => 'تم التسليم', 'cancelled' => 'ملغي'];

    public function allowed(Order $order, Staff $staff): array
    {
        if ($staff->role === 'driver') {
            if ($order->delivery_type !== 'delivery' || ! $order->delivery()->where('driver_staff_id', $staff->id)->exists()) {
                return [];
            }

            return match ($order->status) {
                'assigned' => ['out_for_delivery'], 'out_for_delivery' => ['delivered'], default => [],
            };
        }
        $next = match ($order->status) {
            'pending' => ['confirmed'], 'confirmed' => ['preparing'], 'preparing' => ['ready'],
            'ready' => $order->delivery_type === 'pickup' ? ['delivered'] : [],
            'assigned' => ['out_for_delivery'], 'out_for_delivery' => ['delivered'], default => [],
        };
        if ($staff->role === 'manager' && in_array($order->status, ['pending', 'confirmed', 'preparing', 'ready']) && $order->payment_method === 'cash' && $order->payment_status !== 'paid') {
            $next[] = 'cancelled';
        }

        return $next;
    }

    public function transition(int $id, Staff $staff, string $expected, string $status, ?string $reason): void
    {
        DB::transaction(function () use ($id, $staff, $expected, $status, $reason) {
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($order->status !== $expected || ! in_array($status, $this->allowed($order, $staff))) {
                $this->fail('حالة الطلب تغيّرت أو الانتقال غير مسموح. حدّث الصفحة.');
            }
            if ($order->payment_method !== 'cash' && $order->payment_status !== 'paid') {
                $this->fail('يجب التحقق من الدفع الإلكتروني قبل تجهيز الطلب.');
            }
            if ($status === 'delivered' && $order->payment_status !== 'paid') {
                $this->fail('سجّل استلام المبلغ النقدي قبل تأكيد التسليم.');
            }
            if ($status === 'cancelled') {
                if (! trim($reason ?? '')) {
                    $this->fail('اذكر سبب الإلغاء.');
                }
                $items = $order->items()->orderBy('variant_id')->get();
                if ($items->contains(fn ($item) => $item->stock_reserved === null)) {
                    $this->fail('طلب قديم دون سجل حجز مخزون؛ يحتاج مراجعة المخزون قبل الإلغاء.');
                }
                foreach ($items->where('stock_reserved', true)->groupBy('variant_id') as $variantId => $lines) {
                    $stock = BranchProductVariant::where('branch_id', $order->branch_id)->where('product_variant_id', $variantId)->lockForUpdate()->first();
                    if ($stock && $stock->stock_quantity !== null) {
                        $stock->increment('stock_quantity', $lines->sum('quantity'));
                    }
                }
            }
            $order->update(['status' => $status]);
            $deliveryStatus = match ($status) {
                'out_for_delivery' => 'picked_up','delivered' => 'delivered','cancelled' => 'cancelled',default => null
            };
            if ($deliveryStatus && $order->delivery_type === 'delivery') {
                $changes = ['status' => $deliveryStatus];
                if ($status === 'out_for_delivery') {
                    $changes['picked_up_at'] = now();
                }
                if ($status === 'delivered') {
                    $changes['delivered_at'] = now();
                }
                $order->delivery()->update($changes);
            }
            DB::table('admin_events')->insert(['staff_id' => $staff->id, 'order_id' => $order->id, 'action' => 'status', 'details' => self::LABELS[$expected].' ← '.self::LABELS[$status].($reason ? ' — '.$reason : ''), 'created_at' => now()]);
            $order->user->notifications()->create(['title' => 'تحديث الطلب', 'body' => $order->order_number.' — '.self::LABELS[$status], 'type' => 'order_status', 'reference_id' => (string) $order->id]);
        }, 3);
    }

    public function collect(int $id, Staff $staff): void
    {
        DB::transaction(function () use ($id, $staff) {
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($staff->role === 'driver' && ($order->status !== 'out_for_delivery' || ! $order->delivery()->where('driver_staff_id', $staff->id)->exists())) {
                abort(403);
            }
            if ($order->payment_method !== 'cash' || ! in_array($order->status, ['ready', 'assigned', 'out_for_delivery'])) {
                $this->fail('التحصيل متاح للطلبات النقدية الجاهزة أو في الطريق فقط.');
            }
            if ($order->payment_status === 'paid') {
                return;
            }
            if ($order->payment_status !== 'pending') {
                $this->fail('حالة الدفع تحتاج مراجعة المدير.');
            }
            $payment = $order->payments()->where('payment_method', 'cash')->lockForUpdate()->firstOrFail();
            $payment->update(['status' => 'paid', 'paid_at' => now()]);
            $order->update(['payment_status' => 'paid']);
            DB::table('admin_events')->insert(['staff_id' => $staff->id, 'order_id' => $order->id, 'action' => 'cash_collected', 'details' => 'تم تأكيد استلام '.$order->total.' ر.س نقدًا', 'created_at' => now()]);
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['status' => $message]);
    }
}
