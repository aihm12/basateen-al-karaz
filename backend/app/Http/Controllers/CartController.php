<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Cart;
use App\Models\User;
use App\Services\CartService;
use App\Services\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function show(Request $r, CartService $service)
    {
        return response()->json($service->view($r->user()->cart));
    }

    public function add(Request $r, CartService $service)
    {
        $d = $r->validate(['branch_id' => 'required|integer|exists:branches,id', 'variant_id' => 'required|integer|exists:product_variants,id', 'quantity' => 'required|integer|min:1|max:99', 'option_value_ids' => 'sometimes|array|max:20', 'option_value_ids.*' => 'integer|distinct']);
        $cart = DB::transaction(function () use ($r, $d, $service) {
            User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            Branch::whereKey($d['branch_id'])->where('is_active', true)->firstOrFail();
            $cart = Cart::firstOrCreate(['user_id' => $r->user()->id], ['branch_id' => $d['branch_id']]);
            if ($cart->branch_id != $d['branch_id'] && $cart->items()->exists()) {
                $service->fail('branch_id', 'أفرغ السلة قبل تغيير الفرع.');
            }
            $cart->update(['branch_id' => $d['branch_id']]);
            $totalQuantity = $cart->items()->where('variant_id', $d['variant_id'])->sum('quantity') + $d['quantity'];
            $quote = $service->quote($d['variant_id'], $cart->branch_id, $d['option_value_ids'] ?? [], $totalQuantity);
            $item = $cart->items()->create(['product_id' => $quote['variant']->product_id, 'variant_id' => $d['variant_id'], 'quantity' => $d['quantity'], 'unit_price' => Money::decimal($quote['unit'])]);
            foreach ($quote['values'] as $v) {
                $item->options()->create(['option_value_id' => $v->id, 'price' => $v->additional_price]);
            }

            return $cart;
        });

        return response()->json($service->view($cart), 201);
    }

    public function update(Request $r, int $id, CartService $service)
    {
        $d = $r->validate(['quantity' => 'required|integer|min:1|max:99']);
        $cart = DB::transaction(function () use ($r, $id, $d, $service) {
            User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            $cart = $r->user()->cart()->firstOrFail();
            $item = $cart->items()->findOrFail($id);
            $qty = $cart->items()->where('variant_id', $item->variant_id)->where('id', '!=', $id)->sum('quantity') + $d['quantity'];
            $quote = $service->quote($item->variant_id, $cart->branch_id, $item->options()->pluck('option_value_id')->all(), $qty);
            $item->update(['quantity' => $d['quantity'], 'unit_price' => Money::decimal($quote['unit'])]);
            foreach ($quote['values'] as $v) {
                $item->options()->where('option_value_id', $v->id)->update(['price' => $v->additional_price]);
            }

            return $cart;
        });

        return response()->json($service->view($cart));
    }

    public function remove(Request $r, int $id)
    {
        DB::transaction(function () use ($r, $id) {
            User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            $r->user()->cart()->firstOrFail()->items()->findOrFail($id)->delete();
        });

        return response()->noContent();
    }

    public function clear(Request $r)
    {
        DB::transaction(function () use ($r) {
            User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            $r->user()->cart?->items()->delete();
        });

        return response()->noContent();
    }
}
