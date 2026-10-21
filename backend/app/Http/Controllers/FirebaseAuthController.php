<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kreait\Firebase\JWT\Error\IdTokenVerificationFailed;
use Kreait\Firebase\JWT\IdTokenVerifier;

class FirebaseAuthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(config('firebase.enabled'), 503, 'تسجيل الدخول غير متاح حاليًا.');
        $data = $request->validate(['id_token' => ['required', 'string', 'max:12000'], 'name' => ['nullable', 'string', 'max:120']]);
        try {
            $claims = IdTokenVerifier::createWithProjectId(config('firebase.project_id'))->verifyIdToken($data['id_token'])->payload();
        } catch (IdTokenVerificationFailed) {
            abort(401, 'تعذّر التحقق من تسجيل الدخول.');
        }
        $phone = $claims['phone_number'] ?? '';
        abort_unless(is_string($phone) && preg_match('/^\+9665[0-9]{8}$/', $phone)
            && ($claims['firebase']['sign_in_provider'] ?? null) === 'phone'
            && is_int($claims['auth_time'] ?? null) && $claims['auth_time'] >= time() - 300, 401);
        $user = User::firstOrCreate(['phone' => $phone], ['name' => $data['name'] ?? 'عميل بساتين الكرز', 'phone_verified_at' => now()]);
        abort_unless($user->is_active, 403);
        $user->update(['phone_verified_at' => now()]);

        return response()->json(['token' => $user->createToken('mobile', ['*'], now()->addDays(config('store.token_days')))->plainTextToken, 'token_type' => 'Bearer', 'user' => $user]);
    }
}
