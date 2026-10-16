<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Money;
use App\Services\MoyasarGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function config(MoyasarGateway $gateway): JsonResponse
    {
        return response()->json(['online_enabled' => $gateway->enabled(), 'provider' => 'moyasar', 'mode' => 'test', 'cash_enabled' => true]);
    }

    public function session(Request $request, int $id, MoyasarGateway $gateway): JsonResponse
    {
        $order = $request->user()->orders()->findOrFail($id);
        abort_unless($gateway->enabled(), 503, 'بوابة الدفع التجريبي غير مهيأة.');
        abort_unless($order->payment_method === 'moyasar' && $order->payment_status === 'pending' && $order->status !== 'cancelled', 409, 'الطلب غير متاح للدفع.');
        $payment = DB::transaction(function () use ($order) {
            $payment = $order->payments()->where('provider', 'moyasar')->lockForUpdate()->firstOrFail();
            if ($payment->invoice_id) {
                return $payment;
            }
            abort_unless($payment->session_state === 'new', 409, 'يلزم مراجعة محاولة الدفع السابقة قبل إنشاء رابط جديد.');
            $payment->update(['session_state' => 'creating']);

            return $payment;
        });
        if ($payment->invoice_id) {
            return response()->json(['checkout_url' => $payment->checkout_url, 'mode' => 'test']);
        }
        try {
            $invoice = $gateway->request('POST', 'invoices', [
                'amount' => Money::cents($order->total), 'currency' => 'SAR',
                'description' => $order->order_number,
                'success_url' => config('payments.return_url'), 'back_url' => config('payments.return_url'),
                'expired_at' => now()->addMinutes(30)->toIso8601String(),
            ]);
            $host = parse_url($invoice['url'] ?? '', PHP_URL_HOST);
            abort_unless(isset($invoice['id']) && ($invoice['amount'] ?? null) === Money::cents($order->total)
                && ($invoice['currency'] ?? null) === 'SAR' && ($invoice['description'] ?? null) === $order->order_number
                && parse_url($invoice['url'] ?? '', PHP_URL_SCHEME) === 'https'
                && is_string($host) && ($host === 'moyasar.com' || str_ends_with($host, '.moyasar.com')), 502, 'بيانات رابط الدفع غير صالحة.');
            $payment->update(['invoice_id' => $invoice['id'], 'checkout_url' => $invoice['url'], 'session_state' => 'ready']);
        } catch (\Throwable $error) {
            $payment->update(['session_state' => 'review_required']);
            throw $error;
        }

        return response()->json(['checkout_url' => $payment->checkout_url, 'mode' => 'test']);
    }

    public function verify(Request $request, int $id, MoyasarGateway $gateway): JsonResponse
    {
        $order = $request->user()->orders()->findOrFail($id);
        abort_unless($order->payment_method === 'moyasar', 409, 'الطلب ليس دفعًا إلكترونيًا.');
        $payment = $order->payments()->where('provider', 'moyasar')->firstOrFail();
        abort_unless($payment->invoice_id, 409, 'لم يُنشأ رابط دفع لهذا الطلب.');
        $invoice = $gateway->request('GET', 'invoices/'.rawurlencode($payment->invoice_id));
        abort_unless(($invoice['id'] ?? null) === $payment->invoice_id
            && ($invoice['amount'] ?? null) === Money::cents($order->total)
            && ($invoice['currency'] ?? null) === 'SAR'
            && ($invoice['description'] ?? null) === $order->order_number, 409, 'بيانات الدفع لا تطابق الطلب.');
        if (($invoice['status'] ?? null) === 'paid') {
            DB::transaction(function () use ($order, $payment) {
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
                abort_if($lockedOrder->status === 'cancelled', 409, 'الدفع يحتاج مراجعة بسبب إلغاء الطلب.');
                if ($lockedPayment->status !== 'paid') {
                    $lockedPayment->update(['status' => 'paid', 'paid_at' => now(), 'transaction_id' => $lockedPayment->invoice_id]);
                    $lockedOrder->update(['payment_status' => 'paid']);
                }
            });
        }

        return response()->json(['data' => $order->fresh()->load('payments'), 'provider_status' => $invoice['status'] ?? 'unknown', 'mode' => 'test']);
    }
}
