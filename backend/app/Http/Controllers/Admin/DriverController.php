<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Staff;
use App\Services\AdminOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DriverController extends Controller
{
    private function authorizeOrder(Order $order): void
    {
        abort_unless(Auth::guard('staff')->user()->role === 'driver' && $order->delivery_type === 'delivery' && $order->delivery()->where('driver_staff_id', Auth::guard('staff')->id())->exists(), 403);
    }

    public function index(): View
    {
        abort_unless(Auth::guard('staff')->user()->role === 'driver', 403);
        $orders = Order::with('branch')->whereHas('delivery', fn ($q) => $q->where('driver_staff_id', Auth::guard('staff')->id()))->whereIn('status', ['assigned', 'out_for_delivery', 'delivered'])->latest()->paginate(15);

        return view('admin.driver', ['orders' => $orders, 'labels' => AdminOrderService::LABELS]);
    }

    public function show(Order $order, AdminOrderService $service): View
    {
        $this->authorizeOrder($order);
        $order->load('user', 'branch', 'items.options');

        return view('admin.driver-order', ['order' => $order, 'labels' => AdminOrderService::LABELS, 'allowed' => $service->allowed($order, Auth::guard('staff')->user())]);
    }

    public function status(Request $r, Order $order, AdminOrderService $service): RedirectResponse
    {
        $this->authorizeOrder($order);
        $data = $r->validate(['expected_status' => 'required|string', 'status' => 'required|in:out_for_delivery,delivered']);
        $service->transition($order->id, Auth::guard('staff')->user(), $data['expected_status'], $data['status'], null);

        return back()->with('success', 'تم تحديث حالة التوصيل.');
    }

    public function collect(Request $r, Order $order, AdminOrderService $service): RedirectResponse
    {
        $this->authorizeOrder($order);
        $r->validate(['confirmed' => 'accepted']);
        $service->collect($order->id, Auth::guard('staff')->user());

        return back()->with('success', 'تم تسجيل استلام المبلغ.');
    }

    public function assign(Request $r, Order $order): RedirectResponse
    {
        $data = $r->validate(['driver_staff_id' => 'required|integer', 'expected_driver' => 'nullable|integer', 'expected_status' => 'required|in:ready,assigned']);
        DB::transaction(function () use ($order, $data) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $delivery = $order->delivery()->lockForUpdate()->first();
            if ($order->delivery_type !== 'delivery' || ! $delivery || $order->status !== $data['expected_status'] || ! in_array($order->status, ['ready', 'assigned']) || (string) $delivery->driver_staff_id !== (string) ($data['expected_driver'] ?? '')) {
                throw ValidationException::withMessages(['driver_staff_id' => 'تغيّر الطلب أو المندوب. حدّث الصفحة؛ الإسناد متاح للطلب الجاهز فقط.']);
            }
            $driver = Staff::whereKey($data['driver_staff_id'])->where('role', 'driver')->where('is_active', true)->lockForUpdate()->first();
            if (! $driver || ($order->payment_method !== 'cash' && $order->payment_status !== 'paid')) {
                throw ValidationException::withMessages(['driver_staff_id' => 'اختر مندوبًا فعالًا وتحقق من دفع الطلب الإلكتروني.']);
            }
            $delivery->update(['driver_staff_id' => $driver->id, 'status' => 'assigned', 'assigned_at' => now()]);
            $order->update(['status' => 'assigned']);
            DB::table('admin_events')->insert(['staff_id' => Auth::guard('staff')->id(), 'order_id' => $order->id, 'action' => 'driver_assigned', 'details' => 'إسناد التوصيل إلى '.$driver->name, 'created_at' => now()]);
        }, 3);

        return back()->with('success','تم إسناد الطلب للمندوب.');
    }
}
