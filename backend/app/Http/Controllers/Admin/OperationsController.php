<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProductVariant;
use App\Models\Order;
use App\Models\Product;
use App\Models\Staff;
use App\Services\AdminOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function dashboard(): View
    {
        return view('admin.dashboard', [
            'counts' => Order::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'sales' => Order::where('payment_status', 'paid')->whereDate('created_at', today())->sum('total'),
            'products' => Product::count(),
            'lowStock' => BranchProductVariant::with('variant.product', 'branch')->whereNotNull('stock_quantity')->where('stock_quantity', '<=', 5)->limit(8)->get(),
            'recent' => Order::with('user')->latest()->limit(8)->get(),
            'labels' => AdminOrderService::LABELS,
        ]);
    }

    public function orders(Request $r): View
    {
        $data = $r->validate(['status' => ['nullable', Rule::in(array_keys(AdminOrderService::LABELS))], 'q' => 'nullable|string|max:100', 'branch_id' => 'nullable|exists:branches,id']);
        $query = Order::with('user', 'branch')->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->when($data['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('order_number', 'like', '%'.$term.'%')->orWhereHas('user', fn ($u) => $u->where('phone', 'like', '%'.$term.'%'))));

        return view('admin.orders', ['orders' => $query->latest()->paginate(20)->withQueryString(), 'branches' => Branch::all(), 'labels' => AdminOrderService::LABELS]);
    }

    public function kitchen(): View
    {
        return view('admin.kitchen', ['orders' => Order::with('items.options', 'branch')->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready'])->oldest()->get(), 'labels' => AdminOrderService::LABELS]);
    }

    public function show(Order $order, AdminOrderService $service): View
    {
        $order->load('user', 'items.options', 'branch', 'delivery', 'payments');

        return view('admin.order', ['order' => $order, 'labels' => AdminOrderService::LABELS, 'drivers' => Staff::where('role', 'driver')->where('is_active', true)->get(), 'allowed' => $service->allowed($order, Auth::guard('staff')->user()), 'events' => DB::table('admin_events')->join('staff', 'staff.id', '=', 'admin_events.staff_id')->where('order_id', $order->id)->select('admin_events.*', 'staff.name')->latest('admin_events.id')->get()]);
    }

    public function status(Request $r, Order $order, AdminOrderService $service): RedirectResponse
    {
        $data = $r->validate(['expected_status' => 'required|string', 'status' => ['required', Rule::in(array_keys(AdminOrderService::LABELS))], 'reason' => 'nullable|string|max:500']);
        $service->transition($order->id, Auth::guard('staff')->user(), $data['expected_status'], $data['status'], $data['reason'] ?? null);

        return back()->with('success', 'تم تحديث حالة الطلب.');
    }

    public function collect(Request $r, Order $order, AdminOrderService $service): RedirectResponse
    {
        $r->validate(['confirmed' => 'accepted']);
        $service->collect($order->id, Auth::guard('staff')->user());

        return back()->with('success', 'تم تسجيل استلام المبلغ النقدي.');
    }
}
