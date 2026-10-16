<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Coupon;
use App\Models\DeliverySlot;
use App\Models\DeliveryZone;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function coupons(): View
    {
        return view('admin.coupons', ['coupons' => Coupon::withCount(['orders' => fn ($q) => $q->where('status', '!=', 'cancelled')])->latest()->get()]);
    }

    public function coupon(Request $r, ?Coupon $coupon = null): RedirectResponse
    {
        $r->merge(['code' => strtoupper((string) $r->input('code'))]);
        $data = $r->validate(['code' => ['required', 'alpha_dash', 'max:40', Rule::unique('coupons')->ignore($coupon?->id)],
            'discount_type' => ['required', Rule::in(['fixed', 'percentage'])], 'discount_value' => 'required|numeric|min:0.01|max:'.($r->discount_type === 'percentage' ? '100' : '999999'),
            'minimum_order' => 'required|numeric|min:0|max:999999', 'maximum_discount' => 'nullable|numeric|min:0|max:999999', 'usage_limit' => 'nullable|integer|min:1|max:1000000',
            'usage_per_customer' => 'required|integer|min:1|max:100000', 'first_order_only' => 'required|boolean', 'starts_at' => 'nullable|date',
            'expires_at' => ['nullable', 'date', Rule::when($r->filled('starts_at'), 'after:starts_at')], 'is_active' => 'required|boolean']);
        ($coupon ?? new Coupon)->fill($data)->save();

        return back()->with('success', 'تم حفظ الكوبون.');
    }

    public function delivery(): View
    {
        return view('admin.delivery', ['branches' => Branch::all(), 'zones' => DeliveryZone::with('branch')->get(), 'slots' => DeliverySlot::with('branch')->orderBy('day_of_week')->orderBy('start_time')->get()]);
    }

    public function zone(Request $r, ?DeliveryZone $zone = null): RedirectResponse
    {
        $data = $r->validate(['branch_id' => 'required|exists:branches,id', 'name' => 'required|string|max:255', 'delivery_fee' => 'required|numeric|min:0|max:9999', 'minimum_order' => 'required|numeric|min:0|max:999999', 'free_delivery_above' => 'nullable|numeric|min:0|max:999999', 'estimated_minutes' => 'required|integer|min:1|max:1440', 'districts_text' => 'required|string|max:3000', 'is_active' => 'required|boolean']);
        if ($zone?->exists && $zone->branch_id != $data['branch_id']) {
            throw ValidationException::withMessages(['branch_id' => 'أنشئ منطقة جديدة بدل نقل منطقة لها عناوين إلى فرع آخر.']);
        }
        $data['districts'] = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/u', $data['districts_text'])))));
        unset($data['districts_text']);
        if (! $data['districts']) {
            throw ValidationException::withMessages(['districts_text' => 'أدخل حيًا واحدًا على الأقل.']);
        }
        ($zone ?? new DeliveryZone)->fill($data)->save();

        return back()->with('success', 'تم حفظ منطقة التوصيل.');
    }

    public function slot(Request $r, ?DeliverySlot $slot = null): RedirectResponse
    {
        $data = $r->validate(['branch_id' => 'required|exists:branches,id', 'day_of_week' => 'required|integer|min:0|max:6', 'start_time' => ['required', 'date_format:H:i', Rule::unique('delivery_slots')->where('branch_id', $r->branch_id)->where('day_of_week', $r->day_of_week)->ignore($slot?->id)],
            'end_time' => 'required|date_format:H:i|after:start_time', 'max_orders' => 'required|integer|min:1|max:10000', 'is_active' => 'required|boolean']);
        if ($slot?->exists && $slot->orders()->whereNotIn('status', ['delivered', 'cancelled'])->exists() && ($slot->branch_id != $data['branch_id'] || $slot->day_of_week != $data['day_of_week'] || substr($slot->start_time, 0, 5) !== $data['start_time'] || substr($slot->end_time, 0, 5) !== $data['end_time'])) {
            throw ValidationException::withMessages(['start_time' => 'توجد طلبات نشطة لهذا الموعد؛ أوقفه وأنشئ موعدًا جديدًا.']);
        }
        ($slot ?? new DeliverySlot)->fill($data)->save();

        return back()->with('success', 'تم حفظ الموعد.');
    }

    public function branch(Request $r, Branch $branch): RedirectResponse
    {
        $data = $r->validate(['name' => 'required|string|max:255', 'phone' => 'nullable|string|max:30', 'address' => 'nullable|string|max:1000', 'pickup_enabled' => 'required|boolean', 'delivery_enabled' => 'required|boolean', 'is_active' => 'required|boolean']);
        $branch->update($data);

        return back()->with('success', 'تم حفظ الفرع.');
    }

    public function team(): View
    {
        return view('admin.team', ['team' => Staff::orderBy('id')->get()]);
    }

    public function staff(Request $r, ?Staff $staff = null): RedirectResponse
    {
        $r->merge(['email' => strtolower((string) $r->email)]);
        $data = $r->validate(['name' => 'required|string|max:255', 'email' => ['required', 'email', 'max:255', Rule::unique('staff')->ignore($staff?->id)], 'password' => [$staff ? 'nullable' : 'required', 'confirmed', Password::min(12)->letters()->numbers()], 'role' => ['required', Rule::in(['manager', 'staff', 'driver'])], 'is_active' => 'required|boolean']);
        if ($staff?->id === Auth::guard('staff')->id() && ($data['role'] !== 'manager' || ! $data['is_active'])) {
            throw ValidationException::withMessages(['role' => 'لا يمكنك إيقاف حسابك أو إزالة صلاحيتك الإدارية.']);
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }
        DB::transaction(function () use ($data, $staff) {
            $managers = Staff::where('role', 'manager')->lockForUpdate()->get();
            if ($staff?->role === 'manager' && ($data['role'] !== 'manager' || ! $data['is_active']) && $managers->where('is_active', true)->where('id', '!=', $staff->id)->isEmpty()) {
                throw ValidationException::withMessages(['role' => 'يجب إبقاء مدير فعال واحد على الأقل.']);
            }
            ($staff ?? new Staff)->fill($data)->save();
        });

        return back()->with('success', 'تم حفظ حساب الفريق.');
    }

    public function account(): View
    {
        return view('admin.account');
    }

    public function password(Request $r): RedirectResponse
    {
        $data = $r->validate(['current_password' => 'required|string', 'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]]);
        $staff = Auth::guard('staff')->user();
        if (! Hash::check($data['current_password'], $staff->password)) {
            throw ValidationException::withMessages(['current_password' => 'كلمة المرور الحالية غير صحيحة.']);
        }
        $staff->update(['password' => $data['password']]);
        $r->session()->regenerate();
        $r->session()->put('staff_password_stamp', hash('sha256', $staff->password));

        return back()->with('success', 'تم تغيير كلمة المرور.');
    }
}
