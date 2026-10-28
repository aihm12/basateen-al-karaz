<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Money;
use App\Services\MoyasarGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NativePaymentController extends Controller
{
    public function session(Request $request, int $id, MoyasarGateway $gateway): JsonResponse
    {
        abort_unless($gateway->enabled() && str_starts_with(config('payments.publishable_key', ''), 'pk_test_'), 503, 'الدفع داخل التطبيق قيد التجهيز.');
        $data = DB::transaction(function () use ($request, $id) {
            $order = $request->user()->orders()->lockForUpdate()->findOrFail($id);
            abort_unless($order->payment_method === 'moyasar' && $order->payment_status === 'pending' && $order->status !== 'cancelled', 409, 'الطلب غير متاح للدفع.');
            $payment = $order->payments()->where('provider', 'moyasar')->lockForUpdate()->firstOrFail();
            abort_if($payment->invoice_id || ! in_array($payment->session_state, ['new', 'native']), 409, 'هذا الطلب مرتبط بمحاولة سابقة. راجع نتيجة الدفع من تفاصيل الطلب.');
            if (! $payment->transaction_id) {
                $payment->update(['transaction_id' => (string) Str::uuid(), 'session_state' => 'native']);
            }

            return ['payment_id' => $payment->transaction_id, 'amount' => Money::cents($order->total), 'currency' => 'SAR', 'description' => $order->order_number, 'publishable_key' => config('payments.publishable_key')];
        });

        return response()->json($data);
    }

    public function verify(Request $request, int $id, MoyasarGateway $gateway): JsonResponse
    {
        $order = $request->user()->orders()->findOrFail($id);
        $payment = $order->payments()->where('provider', 'moyasar')->firstOrFail();
        abort_unless($order->payment_method === 'moyasar' && $payment->session_state === 'native' && $payment->transaction_id, 409);
        $result = $gateway->request('GET', 'payments/'.rawurlencode($payment->transaction_id));
        abort_unless(($result['id'] ?? null) === $payment->transaction_id && ($result['amount'] ?? null) === Money::cents($order->total) && ($result['currency'] ?? null) === 'SAR' && ($result['description'] ?? null) === $order->order_number && (! array_key_exists('live', $result) || $result['live'] === false), 409, 'بيانات الدفع لا تطابق الطلب.');
        if (($result['status'] ?? null) === 'paid') {
            DB::transaction(function () use ($order, $payment) {
                $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                abort_if($locked->status === 'cancelled', 409, 'الدفع يحتاج مراجعة بسبب إلغاء الطلب.');
                $record = $locked->payments()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                if ($record->status !== 'paid') {
                    $record->update(['status' => 'paid', 'paid_at' => now()]);
                    $locked->update(['payment_status' => 'paid']);
                }
            });
        }

        return response()->json(['data' => $order->fresh(), 'provider_status' => $result['status'] ?? 'unknown']);
    }
}
