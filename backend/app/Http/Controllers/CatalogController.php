<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DeliverySlot;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function categories()
    {
        return response()->json(['data' => Category::where('is_active', true)->orderBy('sort_order')->get()]);
    }

    private function productsQuery()
    {
        return Product::where('is_active', true)->whereHas('category', fn ($q) => $q->where('is_active', true))->with(['category', 'images', 'variants' => fn ($q) => $q->where('is_active', true), 'optionGroups.values' => fn ($q) => $q->where('is_active', true)]);
    }

    public function products(Request $r)
    {
        $d = $r->validate(['category_id' => 'nullable|integer|exists:categories,id', 'search' => 'nullable|string|max:100', 'per_page' => 'nullable|integer|min:1|max:50']);

        return $this->productsQuery()->when($d['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))->when($d['search'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('name_ar', 'like', '%'.$term.'%')->orWhere('name_en', 'like', '%'.$term.'%')))->orderBy('id')->paginate($d['per_page'] ?? 20);
    }

    public function product(int $id)
    {
        return response()->json(['data' => $this->productsQuery()->findOrFail($id)]);
    }

    public function branches()
    {
        return response()->json(['data' => Branch::where('is_active', true)->with(['deliveryZones' => fn ($q) => $q->where('is_active', true)])->get()]);
    }

    public function slots(Request $r)
    {
        $d = $r->validate(['branch_id' => 'required|integer|exists:branches,id', 'date' => 'required|date_format:Y-m-d|after_or_equal:today|before_or_equal:'.now()->addDays(30)->toDateString()]);
        $date = Carbon::parse($d['date']);
        $slots = DeliverySlot::where('branch_id', $d['branch_id'])->whereHas('branch', fn ($q) => $q->where('is_active', true))->where('is_active', true)->where('day_of_week', $date->dayOfWeek)->withCount(['orders as booked' => fn ($q) => $q->whereDate('delivery_date', $d['date'])->where('status', '!=', 'cancelled')])->get()->filter(fn ($s) => $s->booked < $s->max_orders && Carbon::parse($d['date'].' '.$s->start_time)->isFuture())->values();

        return response()->json(['data' => $slots]);
    }
}
