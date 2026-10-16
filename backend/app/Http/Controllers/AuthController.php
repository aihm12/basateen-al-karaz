<?php

namespace App\Http\Controllers;

use App\Contracts\OtpSender;
use App\Models\OtpChallenge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function send(Request $r, OtpSender $sender)
    {
        $d = $r->validate(['phone' => ['required', 'regex:/^\+9665[0-9]{8}$/']]);
        $code = (string) random_int(100000, 999999);
        $sender->send($d['phone'], $code);
        OtpChallenge::updateOrCreate(['phone' => $d['phone']], ['code_hash' => Hash::make($code), 'attempts' => 0, 'expires_at' => now()->addMinutes(config('store.otp_ttl')), 'consumed_at' => null]);
        $result = ['message' => 'OTP requested', 'expires_in' => 300];
        if (app()->environment(['local', 'testing']) && config('store.otp_driver') === 'local') {
            $result['development_code'] = $code;
        }

        return response()->json($result, 202);
    }

    public function verify(Request $r)
    {
        $d = $r->validate(['phone' => ['required', 'regex:/^\+9665[0-9]{8}$/'], 'code' => ['required', 'digits:6'], 'name' => ['nullable', 'string', 'max:120']]);
        $result = DB::transaction(function () use ($d) {
            $otp = OtpChallenge::where('phone', $d['phone'])->lockForUpdate()->first();
            if (! $otp || $otp->consumed_at || $otp->expires_at->isPast() || $otp->attempts >= 5) {
                return null;
            }
            $otp->increment('attempts');
            if (! Hash::check($d['code'], $otp->code_hash)) {
                return null;
            }
            $user = User::firstOrCreate(['phone' => $d['phone']], ['name' => $d['name'] ?? 'عميل بساتين الكرز', 'phone_verified_at' => now()]);
            if (! $user->is_active) {
                return null;
            }
            $user->update(['phone_verified_at' => now()]);
            $otp->update(['consumed_at' => now()]);

            return ['token' => $user->createToken('mobile', ['*'], now()->addDays(config('store.token_days')))->plainTextToken, 'token_type' => 'Bearer', 'user' => $user];
        });
        if (! $result) {
            throw ValidationException::withMessages(['code' => 'رمز غير صالح أو منتهي الصلاحية.']);
        }

        return response()->json($result);
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
