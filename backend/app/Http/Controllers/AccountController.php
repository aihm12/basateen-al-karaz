<?php

namespace App\Http\Controllers;

use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\User;
use App\Rules\GoogleMapsUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function addresses(Request $r)
    {
        return response()->json(['data' => $r->user()->addresses()->get()]);
    }

    public function addAddress(Request $r)
    {
        $d = $r->validate(['maps_url' => ['nullable', 'string', 'max:2048', new GoogleMapsUrl], 'label' => 'required|string|max:80', 'district' => 'required|string|max:120', 'street' => 'required|string|max:200', 'delivery_zone_id' => 'required|integer|exists:delivery_zones,id', 'latitude' => 'nullable|numeric|between:-90,90', 'longitude' => 'nullable|numeric|between:-180,180', 'building_number' => 'nullable|string|max:40', 'floor' => 'nullable|string|max:20', 'apartment' => 'nullable|string|max:20', 'instructions' => 'nullable|string|max:1000', 'is_default' => 'sometimes|boolean']);
        $zone = DeliveryZone::where('is_active', true)->findOrFail($d['delivery_zone_id']);
        abort_unless(in_array($d['district'], $zone->districts ?? [], true), 422, 'District is outside the selected zone');
        $address = DB::transaction(function () use ($r, $d) {
            User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            if ($d['is_default'] ?? false) {
                $r->user()->addresses()->update(['is_default' => false]);
            }

            return $r->user()->addresses()->create($d);
        });

        return response()->json(['data' => $address], 201);
    }

    public function favorites(Request $r)
    {
        return response()->json(['data' => $r->user()->favorites()->where('is_active', true)->get()]);
    }

    public function favorite(Request $r, int $id)
    {
        Product::where('is_active', true)->findOrFail($id);
        $r->user()->favorites()->syncWithoutDetaching([$id]);

        return response()->noContent();
    }

    public function unfavorite(Request $r, int $id)
    {
        $r->user()->favorites()->detach($id);

        return response()->noContent();
    }
}
