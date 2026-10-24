<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DeviceTokenController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512', 'regex:/^[A-Za-z0-9_:\-]+$/'], 'platform' => ['required', 'in:android,ios']]);
        DeviceToken::upsert([
            ['token' => $data['token'], 'platform' => $data['platform'], 'user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()],
        ], ['token'], ['user_id', 'platform', 'updated_at']);

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);
        $request->user()->deviceTokens()->where('token', $data['token'])->delete();

        return response()->noContent();
    }
}
