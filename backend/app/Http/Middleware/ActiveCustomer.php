<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveCustomer
{
    public function handle(Request $r, Closure $next)
    {
        abort_unless($r->user()?->is_active, 403, 'Account disabled');

        return $next($r);
    }
}
