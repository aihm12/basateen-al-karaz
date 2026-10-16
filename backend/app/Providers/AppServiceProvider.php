<?php

namespace App\Providers;

use App\Contracts\OtpSender;
use App\Services\LocalOtpSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OtpSender::class, LocalOtpSender::class);
    }

    public function boot(): void
    {
        RateLimiter::for('otp-send', fn (Request $r) => [Limit::perMinute(5)->by($r->ip()), Limit::perMinute(1)->by('phone:'.$r->input('phone')), Limit::perDay(20)->by('daily:'.$r->input('phone'))]);
        RateLimiter::for('otp-verify', fn (Request $r) => [Limit::perMinute(10)->by($r->ip()), Limit::perMinute(5)->by('phone:'.$r->input('phone'))]);
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
    }
}
