<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $r)
    {
        return $r->user()->orders()->with('items.options')->latest()->paginate(20);
    }

    public function show(Request $r, int $id)
    {
        return response()->json(['data' => $r->user()->orders()->with('items.options', 'payments', 'delivery')->findOrFail($id)]);
    }

    public function store(Request $r, OrderService $service)
    {
        $d = $r->validate(['delivery_type' => 'required|in:pickup,delivery', 'payment_method' => 'required|in:cash,moyasar', 'address_id' => 'required_if:delivery_type,delivery|prohibited_if:delivery_type,pickup|integer', 'delivery_date' => 'required_if:delivery_type,delivery|prohibited_if:delivery_type,pickup|date_format:Y-m-d|after_or_equal:today|before_or_equal:'.now()->addDays(30)->toDateString(), 'delivery_slot_id' => 'required_if:delivery_type,delivery|prohibited_if:delivery_type,pickup|integer', 'coupon_code' => 'nullable|string|max:50', 'customer_notes' => 'nullable|string|max:1000']);
        $key = $r->header('Idempotency-Key');
        validator(['key' => $key], ['key' => 'required|string|min:8|max:80|regex:/^[a-zA-Z0-9_-]+$/'])->validate();
        $order = $service->checkout($r->user(), $d, $key);

        return response()->json(['data' => $order->load('items.options', 'payments', 'delivery')], 201);
    }
}
