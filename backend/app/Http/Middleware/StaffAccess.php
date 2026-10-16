<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class StaffAccess
{
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $staff = Auth::guard('staff')->user();
        if (! $staff || ! $staff->is_active || ($request->session()->has('staff_password_stamp') && ! hash_equals($request->session()->get('staff_password_stamp'), hash('sha256', $staff->password)))) {
            Auth::guard('staff')->logout();

            return redirect()->route('admin.login');
        }
        $request->session()->put('staff_password_stamp', hash('sha256', $staff->password));
        if ($staff->role === 'driver' && ! $request->routeIs('admin.driver.*', 'admin.account', 'admin.password', 'admin.logout')) {
            if ($request->routeIs('admin.dashboard')) {
                return redirect()->route('admin.driver.index');
            }
            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                return response()->view('admin.access-denied', [], 403)->header('Cache-Control', 'no-store, private');
            }
            abort(403);
        }
        abort_if($role === 'manager' && $staff->role !== 'manager', 403, 'هذه الصفحة متاحة للمدير فقط.');
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
