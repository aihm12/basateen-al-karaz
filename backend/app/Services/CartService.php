<?php

namespace App\Services;

use App\Models\BranchProductVariant;
use App\Models\Cart;
use App\Models\OptionValue;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function quote(int $variantId, int $branchId, array $optionIds, int $quantity): array
    {
        $variant = ProductVariant::with(['product.category', 'product.optionGroups'])->findOrFail($variantId);
        $availability = BranchProductVariant::where('branch_id', $branchId)->where('product_variant_id', $variantId)->lockForUpdate()->first();
        if (! $variant->is_active || ! $variant->product->is_active || ! $variant->product->category->is_active || ! $availability?->is_available || ($availability->stock_quantity !== null && $availability->stock_quantity < $quantity)) {
            $this->fail('variant_id', 'المنتج غير متوفر بالكمية المطلوبة.');
        }
        $values = OptionValue::with('group')->whereIn('id', $optionIds)->where('is_active', true)->get();
        $groups = $variant->product->optionGroups;
        if ($values->count() !== count($optionIds) || $values->contains(fn ($v) => ! $groups->contains('id', $v->option_group_id))) {
            $this->fail('option_value_ids', 'خيار غير صالح لهذا المنتج.');
        }
        foreach ($groups as $group) {
            $count = $values->where('option_group_id', $group->id)->count();
            $min = max($group->min_selection, $group->required ? 1 : 0);
            $max = $group->selection_type === 'single' ? 1 : $group->max_selection;
            if ($count < $min || $count > $max) {
                $this->fail('option_value_ids', 'عدد الخيارات غير صالح: '.$group->name_ar);
            }
        }
        $unit = Money::cents($variant->price) + $values->sum(fn ($v) => Money::cents($v->additional_price));

        return compact('variant', 'values', 'unit', 'availability');
    }

    public function view(?Cart $cart): array
    {
        if (! $cart) {
            return ['data' => null, 'subtotal' => '0.00', 'currency' => 'SAR'];
        }
        $cart->load('items.options.value', 'items.variant', 'items.product');
        $subtotal = $cart->items->sum(fn ($i) => Money::cents($i->unit_price) * $i->quantity);

        return ['data' => $cart, 'subtotal' => Money::decimal($subtotal), 'currency' => 'SAR', 'pricing_note' => 'Cart prices are snapshots; checkout recalculates current prices.'];
    }

    public function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
